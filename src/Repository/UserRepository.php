<?php

namespace App\Repository;

class UserRepository extends RepositoryBase {

	/** @var string[] */
	private $userIds;

	/**
	 * @param string $userId
	 * @return array
	 */
	public function get( $userId ): array {
		$sql = 'SELECT * FROM users WHERE id = :id LIMIT 1';
		$stmt = $this->db->prepare( $sql );
		$result = $stmt->executeQuery( [ 'id' => $userId ] );
		return $result->fetchAssociative();
	}

	/**
	 * @param string $username
	 * @return string
	 */
	public function getIdFromUsername( string $username ): string {
		if ( isset( $this->userIds[$username] ) ) {
			return $this->userIds[$username];
		}
		$find = $this->db->prepare( 'SELECT * FROM users WHERE name = :name' );
		$find->bindValue( 'name', $username );
		$existing = $find->executeQuery();
		if ( $existing->rowCount() === 0 ) {
			$this->db->insert( 'users', [ 'name' => $username ] );
			$existing = $find->executeQuery();
		}
		$user = $existing->fetchAssociative();
		$this->userIds[ $user['name'] ] = $user['id'];
		return $this->userIds[ $user['name'] ];
	}

	public function count(): int {
		$sql = 'SELECT COUNT(DISTINCT user_id) AS tot FROM scores';
		$stmt = $this->db->prepare( $sql );
		$result = $stmt->executeQuery();
		return (int)$result->fetchAssociative()['tot'];
	}
}
