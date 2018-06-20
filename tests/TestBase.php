<?php

namespace Wikisource\WsContest\Tests;

use Symfony\Component\Console\Tester\CommandTester;
use Wikisource\WsContest\Command\UpgradeCommand;

abstract class TestBase extends \PHPUnit\Framework\TestCase {

	public function setUp() {
		global $app;

		// Install application.
		$upgradeCommand = new UpgradeCommand();
		$upgradeCommand->setSlimApp( $app );
		$commandTester = new CommandTester( $upgradeCommand );
		$commandTester->execute( [] );

		// Empty all existing tables.
		$db = $app->getContainer()->get( 'db' );
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
