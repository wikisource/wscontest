<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Wikisource\Api\WikisourceApi;
use Wikisource\Api\WikisourceApiException;

class IndexPageRepository extends RepositoryBase {

	/** @var WikisourceApi */
	private $wikisourceApi;

	/**
	 * @param Connection $connection
	 * @param WikisourceApi $wikisourceApi
	 */
	public function __construct( Connection $connection, WikisourceApi $wikisourceApi ) {
		parent::__construct( $connection );
		$this->wikisourceApi = $wikisourceApi;
	}

	/**
	 * @param array $indexPageUrls
	 * @return array
	 */
	public function saveUrls( array $indexPageUrls ) {
		$query = $this->db->prepare( 'SELECT * FROM index_pages WHERE url = :url' );
		$indexPageIds = [];
		$warnings = [];
		foreach ( $indexPageUrls as $indexPageUrl ) {
			// Do we already know about it?
			$query->bindValue( 'url', $indexPageUrl );
			$result = $query->executeQuery();
			if ( $result->rowCount() > 0 ) {
				$indexPageIds[] = $result->fetchOne();
				continue;
			}

			// Check validity.
			$wikisource = $this->wikisourceApi->newWikisourceFromUrl( $indexPageUrl );
			if ( !$wikisource ) {
				$warnings[] = [ 'error-loading-wikisource', [ $indexPageUrl ] ];
				continue;
			}
			try {
				$wsIndexPage = $wikisource->getIndexPageFromUrl( $indexPageUrl );
			} catch ( WikisourceApiException $e ) {
				$warnings[] = [ 'unable-to-save', [ $e->getMessage() ] ];
				continue;
			}
			if ( !$wsIndexPage->loaded() ) {
				$warnings[] = [ 'error-loading-indexpage', [ $indexPageUrl ] ];
				continue;
			}
			// Save.
			$this->db->insert( 'index_pages', [ 'url' => $indexPageUrl ] );
			$indexPageIds[] = $this->db->lastInsertId();
		}

		return [
			'index_page_ids' => $indexPageIds,
			'warnings' => $warnings,
		];
	}

	/**
	 * @param array $data
	 * @return void
	 */
	public function saveScore( $data ) {
		$sql = 'INSERT INTO scores SET
			index_page_id = :index_page_id,
			contest_id = :contest_id,
			user_id = :user_id,
			revision_id = :revision_id,
			revision_datetime = :revision_datetime,
			points = :points,
			validations = :validations,
			contributions = :contributions
		';
		$this->db->executeQuery( $sql, $data );
	}

	/**
	 * An Index Page needs to be scored if it
	 * is part of a contest,
	 * and one that either: (has no scores) or (is in progress or recently finished).
	 * @param int $scoreCalculationInterval
	 * @return array
	 */
	public function needsScoring( int $scoreCalculationInterval ): array {
		$sql = 'SELECT DISTINCT index_pages.*, contests.id AS contest_id, contests.start_date, contests.end_date
			FROM index_pages
			JOIN contest_index_pages ON (index_pages.id = contest_index_pages.index_page_id)
			LEFT JOIN contests ON (contest_index_pages.contest_id = contests.id)
			LEFT JOIN scores ON (contests.id = scores.contest_id)
			WHERE scores.id IS NULL
				OR contests.end_date > DATE_SUB( NOW(), INTERVAL :mins MINUTE )';
		return $this->db->executeQuery( $sql, [ 'mins' => $scoreCalculationInterval * 3 ] )->fetchAllAssociative();
	}

	/**
	 * @param string $indexPageId
	 * @return void
	 */
	public function deleteScores( $indexPageId ) {
		$this->db->executeStatement( 'DELETE FROM scores WHERE index_page_id = :id', [ 'id' => $indexPageId ] );
	}

	/**
	 * @param string $indexPageId
	 * @return array
	 */
	public function getContests( string $indexPageId ) {
		$sql = 'SELECT contests.* FROM contests
			JOIN contest_index_pages ON (contests.id = contest_index_pages.contest_id)
			WHERE index_page_id = :id';
		return $this->db->executeQuery( $sql, [ 'id' => $indexPageId ] )->fetchAllAssociative();
	}
}
