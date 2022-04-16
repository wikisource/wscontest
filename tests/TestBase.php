<?php

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class TestBase extends KernelTestCase {

	public function setUp(): void {
		self::bootKernel();
		$container = static::getContainer();

		// Empty all existing tables.
		$db = $container->get( Connection::class );
		$db->query( 'SET foreign_key_checks = 0' );
		$db->query( 'TRUNCATE admins' );
		$db->query( 'TRUNCATE excluded_users' );
		$db->query( 'TRUNCATE users' );
		$db->query( 'TRUNCATE index_pages' );
		$db->query( 'TRUNCATE contest_index_pages' );
		$db->query( 'TRUNCATE contests' );
		$db->query( 'TRUNCATE scores' );
		$db->query( 'SET foreign_key_checks = 1' );
	}

}
