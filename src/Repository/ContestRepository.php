<?php

namespace App\Repository;

class ContestRepository extends RepositoryBase {

	/** @const int Public always. */
	public const PRIVACY_PUBLIC = 2;
	/** @const int Only admins during the contest, then public afterwards. */
	public const PRIVACY_ADMIN_DURING = 1;
	/** @const int Private always. */
	public const PRIVACY_PRIVATE = 3;

	/**
	 * @return int
	 */
	public function count(): int {
		$sql = 'SELECT COUNT(*) AS tot FROM contests';
		$stmt = $this->db->prepare( $sql );
		$result = $stmt->executeQuery();
		return (int)$result->fetchAssociative()['tot'];
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @param string[] $values
	 * @return void
	 */
	private function insertMultiple( string $table, string $column, array $values ) {
		$values = array_filter( array_map( 'trim', $values ) );
		if ( empty( $values ) ) {
			return;
		}
		$params = array_map( static function ( $n ) {
			return "(:val_$n)";
		}, array_keys( $values ) );
		$sql = 'INSERT IGNORE INTO ' . $table . ' (' . $column . ') values ' . implode( ', ', $params );
		$stmt = $this->db->prepare( $sql );
		foreach ( $values as $key => $value ) {
			$stmt->bindValue( "val_$key", $value );
		}
		$stmt->executeStatement();
	}

	/**
	 * @param string $joinTable
	 * @param string $subTable
	 * @param string $mainJoinCol
	 * @param string $joinCol
	 * @param string $nameCol
	 * @param string $mainId
	 * @param array $values
	 * @return void
	 */
	private function setJoinValues(
		string $joinTable, string $subTable, string $mainJoinCol, string $joinCol, $nameCol, $mainId, array $values
	): void {
		// Clear join table to start with.
		$this->db->executeStatement( "DELETE FROM $joinTable WHERE $mainJoinCol = :id", [ 'id' => $mainId ] );
		// Clean up the values.
		$values = array_filter( array_map( 'trim', $values ) );
		if ( empty( $values ) ) {
			return;
		}
		// Then insert new values.
		$params = array_map( static function ( $v ) {
			return ":val_$v";
		}, array_keys( $values ) );
		$sql = "INSERT IGNORE INTO $joinTable ($mainJoinCol, $joinCol)
			SELECT :id, $subTable.id FROM $subTable WHERE $subTable.$nameCol IN ( " . implode( ',', $params ) . ' )';
		$stmt = $this->db->prepare( $sql );
		$stmt->bindParam( 'id', $mainId );
		foreach ( $values as $k => $val ) {
			$stmt->bindValue( "val_$k", $val );
		}
		$stmt->executeStatement();
	}

	/**
	 * @param string $id
	 * @param string $name
	 * @param int $privacy
	 * @param string $startDate
	 * @param string $endDate
	 * @param array $admins
	 * @param array $excludedUsers
	 * @param array $indexPages
	 * @return string
	 */
	public function save(
		string $id, string $name, int $privacy, string $startDate, string $endDate,
		array $admins, array $excludedUsers, array $indexPages
	): string {
		$this->db->beginTransaction();

		// Save contest.
		$contestData = [ 'name' => $name, 'privacy' => $privacy, 'start_date' => $startDate, 'end_date' => $endDate ];
		if ( is_numeric( $id ) ) {
			$this->db->update( 'contests', $contestData, [ 'id' => $id ] );
		} else {
			$this->db->insert( 'contests', $contestData );
			$id = $this->db->lastInsertId();
		}

		// Admins.
		$this->insertMultiple( 'users', 'name', $admins );
		$this->setJoinValues( 'admins', 'users', 'contest_id', 'user_id', 'name', $id, $admins );

		// Excluded users.
		$this->insertMultiple( 'users', 'name', $excludedUsers );
		$this->setJoinValues( 'excluded_users', 'users', 'contest_id', 'user_id', 'name', $id, $excludedUsers );

		// Index pages (values have already been inserted).
		$this->setJoinValues(
			'contest_index_pages', 'index_pages', 'contest_id', 'index_page_id', 'url', $id, $indexPages
		);

		$this->db->commit();

		return $id;
	}

	/**
	 * @param string $id
	 * @return array|null
	 */
	public function get( string $id ): ?array {
		$sql = 'SELECT contests.*,
			( start_date <= NOW() AND end_date >= NOW() ) AS in_progress
			FROM contests WHERE id = :id LIMIT 1';
		$stmt = $this->db->prepare( $sql );
		$stmt->bindValue( 'id', $id );
		$result = $stmt->executeQuery()->fetchAssociative();
		if ( !$result ) {
			return null;
		}
		// @hack to fix for PHP < 8.1
		$result['privacy'] = (int)$result['privacy'];
		$result['admins'] = $this->getAdmins( $id );
		$result['excluded_users'] = $this->getExcludedUsers( $id );

		$sql2 = 'SELECT index_pages.*
			FROM index_pages
				JOIN contest_index_pages ON index_pages.id = contest_index_pages.index_page_id
			WHERE contest_index_pages.contest_id = :cid
			ORDER BY index_pages.url';
		$admins = $this->db->prepare( $sql2 );
		$result2 = $admins->executeQuery( [ 'cid' => $id ] );
		$result['index_pages'] = $result2->fetchAllAssociative();

		return $result;
	}

	/**
	 * @param string $contestId
	 * @param string $adminUsername
	 * @return bool
	 */
	public function hasAdmin( string $contestId, string $adminUsername ): bool {
		$sql = 'SELECT contests.*
			FROM contests
				JOIN admins ON ( contests.id = admins.contest_id )
				JOIN users ON ( admins.user_id = users.id )
			WHERE contests.id = :contest_id
				AND users.name LIKE :username
			LIMIT 1';
		$stmt = $this->db->prepare( $sql );
		$stmt->bindValue( 'contest_id', $contestId );
		$stmt->bindValue( 'username', $adminUsername );
		return $stmt->executeQuery()->rowCount() > 0;
	}

	/**
	 * @param string $contestId
	 * @return array
	 */
	public function getScores( string $contestId ): array {
		$sql = 'SELECT
			users.name AS username,
			users.id AS user_id,
			SUM(points) AS points,
			SUM(contributions) AS contributions,
			SUM(validations) AS validations
		FROM scores
			JOIN users ON ( users.id = scores.user_id )
			LEFT JOIN excluded_users ON (
				users.id = excluded_users.user_id
				AND excluded_users.contest_id = scores.contest_id
			) 
		WHERE excluded_users.user_id IS NULL
			AND scores.contest_id = :contest_id
		GROUP BY users.id
		ORDER BY SUM(points) DESC, SUM(contributions) DESC, SUM(validations) DESC
		';
		$stmt = $this->db->prepare( $sql );
		$stmt->bindValue( 'contest_id', $contestId );
		$result = $stmt->executeQuery();
		return $result->fetchAllAssociative();
	}

	/**
	 * @param string $userId
	 * @param string $contestId
	 * @return array
	 */
	public function getScoresForUser( string $userId, string $contestId ): array {
		$sql = 'SELECT scores.*, index_pages.url AS index_page_url FROM scores
			JOIN index_pages ON (scores.index_page_id = index_pages.id)
			WHERE contest_id = :cid AND user_id = :uid
			ORDER BY revision_datetime';
		$stmt = $this->db->prepare( $sql );
		return $stmt->executeQuery( [ 'cid' => $contestId, 'uid' => $userId ] )
			->fetchAllAssociative();
	}

	/**
	 * @param string $username
	 * @return array
	 */
	public function getContestsForUser( string $username ): array {
		$sql = 'SELECT c.* FROM contests c'
		. '   LEFT JOIN admins a ON a.contest_id=c.id '
		. '   LEFT JOIN users u ON u.id=a.user_id'
		. ' WHERE (u.name IS NULL OR u.name = :username )';
		$contests = $this->db->prepare( $sql );
		$result = $contests->executeQuery( [ 'username' => $username ] );
		$contests = $result->fetchAllAssociative();
		foreach ( $contests as &$contest ) {
			$contest['admins'] = $this->getAdmins( $contest['id'] );
		}
		return $contests;
	}

	/**
	 * Get most recent 50 contests (by end date).
	 * @return mixed[][]
	 */
	public function getRecentlyEndedContests(): array {
		$sql = 'SELECT DISTINCT c.*, '
		. ' ( c.start_date >= NOW() ) AS pending, '
		. ' ( c.start_date <= NOW() AND c.end_date >= NOW() ) AS in_progress '
		. ' FROM contests c'
		. '   LEFT JOIN admins a ON a.contest_id=c.id '
		. '   LEFT JOIN users u ON u.id=a.user_id'
		. ' WHERE '
		. '     ( end_date < NOW() AND c.privacy IN (' . self::PRIVACY_PUBLIC . ',' . self::PRIVACY_ADMIN_DURING . ') )'
		. '     OR ( end_date > NOW() AND c.privacy = ' . self::PRIVACY_PUBLIC . ' )'
		. ' ORDER BY end_date DESC'
		. ' LIMIT 50';
		return $this->db->executeQuery( $sql )->fetchAllAssociative();
	}

	/**
	 * @param string $contestId
	 * @return array
	 */
	private function getAdmins( $contestId ): array {
		$sql2 = 'SELECT u.* FROM users u JOIN admins a ON a.user_id=u.id WHERE a.contest_id = :cid ORDER BY u.name';
		$admins = $this->db->prepare( $sql2 );
		$result2 = $admins->executeQuery( [ 'cid' => $contestId ] );
		return $result2->fetchAllAssociative();
	}

	/**
	 * @param array $contest
	 * @param ?string $username
	 * @return bool
	 */
	public function canBeViewedBy( array $contest, ?string $username ): bool {
		$isAdmin = false;
		foreach ( $contest['admins'] as $admin ) {
			$isAdmin = $isAdmin || $admin['name'] === $username;
		}
		if ( $isAdmin ) {
			return true;
		}
		return ( $contest['privacy'] === self::PRIVACY_PUBLIC )
			|| ( $contest['privacy'] === self::PRIVACY_ADMIN_DURING && $contest['in_progress'] && $isAdmin )
			|| ( $contest['privacy'] === self::PRIVACY_ADMIN_DURING && !$contest['in_progress'] )
			|| ( $contest['privacy'] === self::PRIVACY_PRIVATE && $isAdmin );
	}

	/**
	 * @param string $contestId
	 * @return array
	 */
	private function getExcludedUsers( $contestId ): array {
		$sql2 = 'SELECT u.*
			FROM users u JOIN excluded_users ON excluded_users.user_id=u.id
			WHERE excluded_users.contest_id = :cid
			ORDER BY u.name';
		$admins = $this->db->prepare( $sql2 );
		$result2 = $admins->executeQuery( [ 'cid' => $contestId ] );
		return $result2->fetchAllAssociative();
	}

	/**
	 * @param string $contestId
	 * @return void
	 */
	public function deleteScores( $contestId ): void {
		$this->db->executeStatement( 'DELETE FROM scores WHERE contest_id = :id', [ 'id' => $contestId ] );
	}

	/**
	 * @param string $contestId
	 * @return void
	 */
	public function deleteAdmins( $contestId ): void {
		$this->db->executeStatement( 'DELETE FROM admins WHERE contest_id = :id', [ 'id' => $contestId ] );
	}

	/**
	 * @param string $contestId
	 * @return void
	 */
	public function deleteContestIndexPages( $contestId ): void {
		$this->db->executeStatement( 'DELETE FROM contest_index_pages WHERE contest_id = :id', [ 'id' => $contestId ] );
	}

	/**
	 * @param string $contestId
	 * @return void
	 */
	public function deleteExcludedUsers( $contestId ): void {
		$this->db->executeStatement( 'DELETE FROM excluded_users WHERE contest_id = :id', [ 'id' => $contestId ] );
	}

	/**
	 * @param string $contestId
	 * @return void
	 */
	public function deleteContest( $contestId ): void {
		$this->db->beginTransaction();

		/**
		 * NOTE: Data in `users` table and `index_pages` table
		 * is PRESERVED even if all other data about the contest
		 * is deleted.
		 */

		// delete tables containing foreign keys
		$this->deleteAdmins( $contestId );
		$this->deleteExcludedUsers( $contestId );
		$this->deleteContestIndexPages( $contestId );
		$this->deleteScores( $contestId );

		// delete the contest
		$sql2 = 'DELETE FROM contests WHERE id = :id';
		$this->db->executeStatement( $sql2, [ 'id' => $contestId ] );

		// perform a COMMIT on the database
		$this->db->commit();
	}
}
