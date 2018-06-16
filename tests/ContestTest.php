<?php

namespace Wikisource\WsContest\Tests;

use Wikisource\WsContest\Entity\Contest;

class ContestTest extends TestBase {

	/**
	 * A contest has a name, a start date (defaults to today).
	 * @covers \Wikisource\WsContest\Entity\Contest
	 */
	public function testContestBasics() {
		$contest = new Contest();
		$contest->name = 'Test contest';
		$contest->save();
		$this->assertEquals( date( 'Y-m-d 00:00:01' ), $contest->start_date );
		$contest->start_date = '2018-01-02 3:04';
		$contest->save();
		$this->assertEquals( 'Test contest', $contest->name );
		$this->assertEquals( '2018-01-02 03:04:00', $contest->start_date );
	}
}
