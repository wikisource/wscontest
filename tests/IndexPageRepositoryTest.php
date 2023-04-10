<?php

namespace App\Tests;

use App\Repository\IndexPageRepository;

class IndexPageRepositoryTest extends TestBase {

	/**
	 * @covers IndexPageRepository::saveUrls()
	 * @dataProvider provideSaveUrls()
	 * @return void
	 */
	public function testSaveUrls( $urls, $expected ) {
		/** @var IndexPageRepository */
		$repo = static::getContainer()->get( IndexPageRepository::class );
		$actual = $repo->saveUrls( $urls );
		$this->assertSame( $expected, $actual );
	}

	public function provideSaveUrls(): array {
		return [
			[
				[ 'https://jv.wikisource.org/wiki/Indhèks:Babad Saka Kitab Sutji.pdf' ],
				[
					'index_page_ids' => [ '1' ],
					'warnings' => [],
				]
			],
		];
	}
}
