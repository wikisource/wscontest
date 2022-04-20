<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

abstract class RepositoryBase {

	/** @var Connection */
	protected $db;

	/**
	 * @param Connection $db
	 */
	public function __construct( Connection $db ) {
		$this->db = $db;
	}
}
