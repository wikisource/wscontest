<?php

namespace App\Command;

use App\Repository\IndexPageRepository;
use App\Repository\UserRepository;
use DateInterval;
use Mediawiki\Api\FluentRequest;
use Mediawiki\Api\MediawikiApi;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wikisource\Api\IndexPage as WikisourceIndexPage;
use Wikisource\Api\Wikisource;
use Wikisource\Api\WikisourceApi;

class ScoreCommand extends Command {

	/** @var IndexPageRepository */
	private $indexPageRepository;

	/** @var UserRepository */
	private $userRepository;

	/** @var CacheItemPoolInterface */
	private $cache;

	/** @var SymfonyStyle */
	private $io;

	/**
	 * @param IndexPageRepository $indexPageRepository
	 * @param UserRepository $userRepository
	 * @param CacheItemPoolInterface $cache
	 */
	public function __construct(
		IndexPageRepository $indexPageRepository,
		UserRepository $userRepository,
		CacheItemPoolInterface $cache
	) {
		parent::__construct();
		$this->indexPageRepository = $indexPageRepository;
		$this->userRepository = $userRepository;
		$this->cache = $cache;
	}

	/**
	 * Configures the current command.
	 */
	protected function configure() {
		parent::configure();
		$this->setName( 'score' );
		$this->setDescription( 'Retrieve scores from Wikisources.' );
	}

	/**
	 * Executes the current command.
	 * @see setCode()
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return null|int Null or 0 if everything went fine, or an error code.
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->io = new SymfonyStyle( $input, $output );
		$wikisourceApi = new WikisourceApi();
		$wikisourceApi->setCache( $this->cache );
		$indexPages = $this->indexPageRepository->needsScoring();
		foreach ( $indexPages as $indexPage ) {
			// Set up the Wikisource bits.
			$wikisource = $wikisourceApi->newWikisourceFromUrl( $indexPage['url'] );
			if ( !$wikisource ) {
				$this->io->error(
					'Unable to determine Wikisource from URL: ' . $indexPage['url']
				);
				continue;
			}
			$wsIndexPage = $wikisource->getIndexPageFromUrl( $indexPage['url'] );
			if ( !$wsIndexPage->loaded() ) {
				$this->io->error( 'Unable to load Index page from URL: ' . $indexPage['url'] );
				continue;
			}

			$this->io->writeln( 'Scoring: ' . $indexPage['url'] );

			// Delete old scores.
			$this->indexPageRepository->deleteScores( $indexPage['id'] );

			// Go through each contest that uses this Index Page and save the score.
			foreach ( $this->indexPageRepository->getContests( $indexPage['id'] ) as $contest ) {
				// Save new scores.
				$this->calculateScore(
					$wikisource,
					$wsIndexPage->getTitle(),
					$contest,
					$indexPage['id']
				);
			}
		}

		return 0;
	}

	/**
	 * @param Wikisource $wikisource
	 * @param string $indexPageTitle
	 * @param array $contest
	 * @param int $indexPageId
	 */
	protected function calculateScore(
		Wikisource $wikisource, string $indexPageTitle, array $contest, int $indexPageId
	) {
		$wsIndexPage = new WikisourceIndexPage( $wikisource );

		$wsIndexPage->loadFromTitle( $indexPageTitle );
		$api = $wikisource->getMediawikiApi();
		foreach ( $wsIndexPage->getPageList( true ) as $page ) {
			$this->processPage( $contest, $api, $page['title'], $indexPageId );
		}
	}

	/**
	 * @param array $contest
	 * @param MediawikiApi $api
	 * @param string $pageTitle
	 * @param int $indexPageId
	 */
	protected function processPage(
		array $contest, MediawikiApi $api, string $pageTitle, int $indexPageId
	) {
		$cacheItem = $this->cache->getItem( md5( 'revisions_' . $pageTitle ) );
		if ( $cacheItem->isHit() ) {
			$response = $cacheItem->get();
		} else {
			$this->io->writeln( "Fetching revisions of $pageTitle", SymfonyStyle::VERBOSITY_VERBOSE );
			$cacheItem->expiresAfter( new DateInterval( 'P1D' ) );
			$response = $api->getRequest( FluentRequest::factory()
				->setAction( 'query' )
				->setParam( 'prop', 'revisions' )
				->setParam( 'titles', $pageTitle )
				->setParam( 'rvlimit', 5000 )
				->setParam( 'rvdir', 'newer' )
				->setParam( 'rvprop', 'user|timestamp|content|ids' ) );
			$cacheItem->set( $response );
			$this->cache->save( $cacheItem );
		}

		// Go through the page's revisions.
		$pageInfo = array_shift( $response['query']['pages'] );
		if ( !isset( $pageInfo['revisions'] ) ) {
			return;
		}

		$oldTimestamp = false;
		$oldQuality = false;
		$oldUser = false;
		$pattern = '|<pagequality level="(\d)" user="(.+?)" />|';
		foreach ( $pageInfo['revisions'] as $rev ) {
			$content = $rev['*'];
			$matched = preg_match( $pattern, $content, $qualityMatches );
			if ( $matched !== 1 ) {
				// Some revisions don't have a pagequality user.
				continue;
			}
			$quality = (int)$qualityMatches[1];
			$userId = $this->userRepository->getIdFromUsername( $qualityMatches[2] );
			$timestamp = strtotime( $rev['timestamp'] );
			$this->processRevision(
				$indexPageId, $rev['revid'], $contest,
				$quality, $userId, $timestamp, $oldQuality, $oldUser, $oldTimestamp
			);
			$oldQuality = $quality;
			$oldUser = $userId;
			$oldTimestamp = $timestamp;
		}
	}

	/**
	 * @param int $indexPageId
	 * @param int $revisionId
	 * @param array $contest
	 * @param int $quality
	 * @param string $userId
	 * @param int $timestamp
	 * @param int|bool $oldQuality
	 * @param int|false $oldUserId
	 * @param int $oldTimestamp
	 */
	protected function processRevision(
		$indexPageId, $revisionId, array $contest, $quality, $userId, $timestamp, $oldQuality,
		$oldUserId, $oldTimestamp
	) {
		$data = [
			$userId => [ 'points' => 0, 'contributions' => 0, 'validations' => 0 ],
		];
		if ( $oldUserId ) {
			$data[$oldUserId] = [ 'points' => 0, 'contributions' => 0, 'validations' => 0 ];
		}
		$contestStart = strtotime( $contest['start_date'] );
		$contestEnd = strtotime( $contest['end_date'] );

		// Page moved to 3 from anything lower (including not existing).
		if ( $quality === 3 && ( !$oldQuality || $oldQuality < 3 )
			&& $timestamp >= $contestStart && $timestamp < $contestEnd
		) {
			$data[$userId]['points'] += 3;
			$data[$userId]['contributions'] += 1;
		}

		// Page moved from 3 to 4.
		if ( $quality === 4 && $oldQuality === 3 && $timestamp >= $contestStart &&
			 $timestamp < $contestEnd
		) {
			$data[$userId]['points'] += 1;
			$data[$userId]['validations'] += 1;
			$data[$userId]['contributions'] += 1;
		}

		// Page moved from 4 to 3 (even after the end of the contest).
		if ( $quality === 3 && $oldQuality === 4 && $timestamp >= $contestStart
			&& $oldTimestamp >= $contestStart && $oldTimestamp <= $contestEnd
			&& $oldUserId
		) {
			$data[$oldUserId]['points'] -= 1;
			$data[$oldUserId]['validations'] -= 1;
			$data[$oldUserId]['contributions'] -= 1;
		}

		// Page moved from 3 to anything lower.
		if ( $quality < 3 && $oldQuality === 3 && $timestamp >= $contestStart
			&& $oldTimestamp >= $contestStart && $oldTimestamp <= $contestEnd
			&& $oldUserId
		) {
			$data[$oldUserId]['points'] -= 3;
			$data[$oldUserId]['contributions'] -= 1;
		}

		foreach ( $data as $dataUserId => $scores ) {
			if ( !$scores['points'] && !$scores['validations'] && !$scores['contributions'] ) {
				continue;
			}
			$this->indexPageRepository->saveScore( [
				'contest_id' => $contest['id'],
				'index_page_id' => $indexPageId,
				'user_id' => $dataUserId,
				'revision_id' => $revisionId,
				'revision_datetime' => date( 'Y-m-d H:i:s', $timestamp ),
				'points' => $scores['points'],
				'validations' => $scores['validations'],
				'contributions' => $scores['contributions'],
			] );
		}
	}

}
