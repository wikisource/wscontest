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
		$db->executeQuery( 'SET foreign_key_checks = 0' );
		$db->executeQuery( 'TRUNCATE admins' );
		$db->executeQuery( 'TRUNCATE excluded_users' );
		$db->executeQuery( 'TRUNCATE users' );
		$db->executeQuery( 'TRUNCATE index_pages' );
		$db->executeQuery( 'TRUNCATE contest_index_pages' );
		$db->executeQuery( 'TRUNCATE contests' );
		$db->executeQuery( 'TRUNCATE scores' );
		$db->executeQuery( 'SET foreign_key_checks = 1' );
	}

}
