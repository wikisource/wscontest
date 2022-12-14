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
			ContestRepository::PRIVACY_PRIVATE,
			'2022-01-01',
			'2022-02-01',
			[ 'A Admin', 'B Admin' ],
			[ 'Excl. user' ],
			[ 'https://exmaple.com', 'page 2' ]
		);
		$this->assertSame( 1, $repo->count() );
	}

	/**
	 * @dataProvider provideCanBeViewedBy()
	 * @covers ContestRepository::canBeViewedBy()
	 */
	public function testCanBeViewedBy(
		int $privacy, string $startDate, string $endDate, bool $canAdmin, bool $canNonAdmin, bool $canNull
	) {
		/** @var ContestRepository */
		$repo = static::getContainer()->get( ContestRepository::class );
		$id = $repo->save(
			'',
			'Test',
			$privacy,
			$startDate,
			$endDate,
			[ 'A Admin', 'B Admin' ],
			[ 'Excl. user' ],
			[ 'https://exmaple.com', 'page 2' ]
		);
		$contest = $repo->get( $id );
		$this->assertIsInt( $contest['privacy'] );
		$this->assertSame( $canAdmin, $repo->canBeViewedBy( $contest, 'A Admin' ) );
		$this->assertSame( $canNonAdmin, $repo->canBeViewedBy( $contest, 'Excl. user' ) );
		$this->assertSame( $canNull, $repo->canBeViewedBy( $contest, null ) );
	}

	public function provideCanBeViewedBy() {
		return [
			'private during' => [ ContestRepository::PRIVACY_PRIVATE, '2022-01-01', '2030-01-01', true, false, false ],
			'private ended' => [ ContestRepository::PRIVACY_PRIVATE, '2022-01-01', '2022-02-01', true, false, false ],
			'public during' => [ ContestRepository::PRIVACY_PUBLIC, '2022-01-01', '2030-02-01', true, true, true ],
		];
	}
}
