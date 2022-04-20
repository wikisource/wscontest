<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ScoreCommandTest extends TestBase {

	/**
	 * @covers ScoreCommand::execute()
	 * @return void
	 */
	public function testSave() {
		$kernel = self::bootKernel();
		$application = new Application( $kernel );
		$commandTester = new CommandTester( $application->find( 'score' ) );
		$commandTester->execute( [] );
		$commandTester->assertCommandIsSuccessful();
	}
}
