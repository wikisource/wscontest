<?php

namespace App\Tests;

use App\Repository\ContestRepository;

class ContestRepositoryTest extends TestBase {

	/**
	 * @covers ContestRepository::save()
	 * @return void
	 */
	public function testSave() {
		/** @var ContestRepository */
		$repo = static::getContainer()->get( ContestRepository::class );

		$repo->save(
			'',
			'Test',
			'2022-01-01',
			'2022-02-01',
			[ 'A Admin', 'B Admin' ],
			[ 'Excl. user' ],
			[ 'https://exmaple.com', 'page 2' ]
		);
		$this->assertSame( 1, $repo->count() );
	}
}
