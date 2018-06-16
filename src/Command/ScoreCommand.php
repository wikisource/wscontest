<?php

namespace Wikisource\WsContest\Command;

use Mediawiki\Api\FluentRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wikisource\Api\IndexPage as WikisourceIndexPage;
use Wikisource\Api\Wikisource;
use Wikisource\Api\WikisourceApi;
use Wikisource\WsContest\Entity\Contest;
use Wikisource\WsContest\Entity\IndexPage;
use Wikisource\WsContest\Entity\Score;
use Wikisource\WsContest\Entity\User;

class ScoreCommand extends Command {

	/**
	 * Configures the current command.
	 */
	protected function configure() {
		parent::configure();
		$this->setName( 'score' );
		$this->setDescription( 'Retrieve scores from Wikisources.' );
	}

	protected $points;

	/**
	 * Executes the current command.
	 * @see setCode()
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return null|int Null or 0 if everything went fine, or an error code.
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$wikisourceApi = new WikisourceApi();
		$indexPages = IndexPage::all();
		foreach ( $indexPages as $indexPage ) {
			// Set up the Wikisource bits.
			$wikisource = $wikisourceApi->newWikisourceFromUrl( $indexPage->url );
			if ( !$wikisource ) {
				$output->writeln(
					'Unable to determine Wikisource from URL: ' . $indexPage->url
				);
				continue;
			}
			$wsIndexPage = $wikisource->getIndexPageFromUrl( $indexPage->url );
			if ( !$wsIndexPage->loaded() ) {
				$output->writeln( 'Unable to load Index page from URL: ' . $indexPage->url );
				continue;
			}

			// Delete old scores.
			Score::where( [ 'index_page_id' => $indexPage->id ] )->delete();

			// Go through each contest that uses this IndexPage and save the score.
			foreach ( $indexPage->contests()->get() as $contest ) {
				// Save new scores.
				$this->calculateScore(
					$wikisource,
					$wsIndexPage->getTitle(),
					$contest,
					$indexPage->id
				);
			}
		}

		return 0;
	}

	/**
	 * @param Wikisource $wikisource
	 * @param string $indexPageTitle
	 * @param Contest $contest
	 * @param int $indexPageId
	 */
	protected function calculateScore(
		Wikisource $wikisource, $indexPageTitle, Contest $contest, $indexPageId
	) {
		$wsIndexPage = new WikisourceIndexPage( $wikisource );

		$wsIndexPage->loadFromTitle( $indexPageTitle );
		$api = $wikisource->getMediawikiApi();
		foreach ( $wsIndexPage->getPageList() as $page ) {
			$this->processPage( $contest, $api, $page['title'], $indexPageId );
		}
	}

	/**
	 * @param Contest $contest
	 * @param WikisourceApi $api
	 * @param string $pageTitle
	 * @param int $indexPageId
	 */
	protected function processPage( Contest $contest, $api, $pageTitle, $indexPageId ) {
		// @TODO fix for 50 revisions limit.
		$response = $api->getRequest( FluentRequest::factory()
			->setAction( 'query' )
			->setParam( 'prop', 'revisions' )
			->setParam( 'titles', $pageTitle )
			->setParam( 'rvlimit', 50 )
			->setParam( 'rvprop', 'user|timestamp|content|ids' ) );
		// Go through the page's revisions.
		$pageInfo = array_shift( $response['query']['pages'] );
		if ( !isset( $pageInfo['revisions'] ) ) {
			return;
		}

		$oldTimestamp = false;
		$oldQuality = false;
		$oldUser = false;
		foreach ( $pageInfo['revisions'] as $rev ) {
			$content = $rev['*'];
			preg_match( '|<pagequality level="(\d)" user="(.*?)" />|', $content, $qualityMatches );
			$quality = (int)$qualityMatches[1];
			$userId = User::firstOrCreate( [ 'name' => $qualityMatches[2] ] )->id;
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
	 * @param Contest $contest
	 * @param int $quality
	 * @param int $userId
	 * @param int $timestamp
	 * @param int $oldQuality
	 * @param int $oldUserId
	 * @param int $oldTimestamp
	 */
	protected function processRevision(
		$indexPageId, $revisionId, Contest $contest, $quality, $userId, $timestamp, $oldQuality,
		$oldUserId, $oldTimestamp
	) {
		$data = [
			$userId => [ 'points' => 0, 'contributions' => 0, 'validations' => 0 ],
		];
		if ( $oldUserId ) {
			$data[$oldUserId] = [ 'points' => 0, 'contributions' => 0, 'validations' => 0 ];
		}
		$contestStart = strtotime( $contest->start_date );
		$contestEnd = strtotime( $contest->end_date );

		// Page moved to 3 from anything lower (including not existing).
		if ( $quality === 3 && ( !$oldQuality || $oldQuality < 3 )
			&& $timestamp >= $contestStart && $timestamp < $contestEnd
		) {
			$data[$userId]['points'] += 3;
			$data[$userId]['contributions'] += 1;
		}

		// Page moved from 3 to 4.
		if ( $quality === 4 && $oldQuality === 3 && $timestamp >= $contestStart &&
			 $timestamp < $contestEnd ) {
			$data[$userId]['points'] += 1;
			$data[$userId]['validations'] += 1;
			$data[$userId]['contributions'] += 1;
		}

		// Page moved from 4 to 3 (even after the end of the contest).
		if ( $quality === 3 && $oldQuality === 4 && $timestamp >= $contestStart &&
			 $oldTimestamp >= $contestStart && $oldTimestamp <= $contestEnd ) {
			$data[$oldUserId]['points'] -= 1;
			$data[$oldUserId]['validations'] -= 1;
			$data[$oldUserId]['contributions'] -= 1;
		}

		// Page moved from 3 to anything lower.
		if ( $quality < 3 && $oldQuality === 3 && $timestamp >= $contestStart &&
			 $oldTimestamp >= $contestStart && $oldTimestamp <= $contestEnd ) {
			$data[$oldUserId]['points'] -= 3;
			$data[$oldUserId]['contributions'] -= 1;
		}

		foreach ( $data as $dataUserId => $scores ) {
			if ( !$scores['points'] && !$scores['validations'] && !$scores['contributions'] ) {
				continue;
			}
			$score = Score::firstOrNew( [
				'contest_id' => $contest->id,
				'index_page_id' => $indexPageId,
				'user_id' => $dataUserId,
				'revision_id' => $revisionId,
			] );
			$score->revision_datetime = date( 'Y-m-d H:i:s', $timestamp );
			$score->points = $scores['points'];
			$score->validations = $scores['validations'];
			$score->contributions = $scores['contributions'];
			$score->save();
		}
	}

}
