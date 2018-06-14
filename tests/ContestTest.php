<?php

namespace Wikisource\WsContest\Tests;

use Wikisource\WsContest\Entity\Contest;

class ContestTest extends TestBase {

	/**
	 * A contest has a name, a start date (defaults to today), and an end date (two weeks after
	 * the start date).
	 */
	public function testContestBasics() {
		$contest = new Contest;
		$contest->name = 'Test contest';
		$contest->start_date = '2018-01-02 3:04';
		$contest->save();
		$this->assertEquals( 'Test contest', $contest->name );
		$this->assertEquals( '2018-01-02 03:04:00', $contest->start_date );
		$this->assertEquals( '2018-01-16 23:59:59', $contest->end_date );
	}
}
