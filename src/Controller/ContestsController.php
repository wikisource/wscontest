<?php

namespace Wikisource\WsContest\Controller;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Slim\Http\Request;
use Slim\Http\Response;
use Wikisource\WsContest\Entity\Contest;
use Wikisource\WsContest\Entity\IndexPage;
use Wikisource\WsContest\Entity\Score;
use Wikisource\WsContest\Entity\User;
use Wikisource\WsContest\Str;

class ContestsController extends Controller {

	/**
	 * @param Request $request
	 * @param Response $response
	 * @param string[] $args
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function index( Request $request, Response $response, $args ) {
		$username = isset( $_SESSION['username'] ) ? $_SESSION['username'] : false;
		if ( $username ) {
			$contests = Contest::whereHas( 'admins', function ( Builder $query ) use ( $username ) {
				$query->where( 'name', 'LIKE', $username );
			} )->get();
		} else {
			$this->setFlash( 'not-logged-in', 'warning' );
			$contests = [];
		}

		return $this->renderView( $response, 'contests.html.twig', [
			'contests' => $contests,
		] );
	}

	/**
	 * @param Response $response
	 * @param Contest $contest
	 * @param int $userId
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	protected function viewUser( $response, $contest, $userId ) {
		$scores = Score::where( 'user_id', $userId )
			->whereHas( 'indexPage', function ( Builder $b ) use ( $contest ) {
				return $b->where( 'contest_id', $contest->id );
			} )
			->with( 'indexPage' )
			->orderBy( 'revision_datetime' )
			->get();
		return $this->renderView( $response, 'contests_viewuser.html.twig', [
			'contest' => $contest,
			'scores' => $scores,
			'user' => User::find( $userId ),
		] );
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 * @param string[] $args
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function view( Request $request, Response $response, $args ) {
		// Find the contest.
		$id = $request->getAttribute( 'id' );
		$contest = Contest::find( $id );
		if ( !$contest ) {
			$this->setFlash( 'contest-not-found', 'warning', [ $id ] );
			return $response->withRedirect( $this->router->urlFor( 'contests' ) );
		}

		// If a user ID is requested, show only the scores for that user.
		$userId = $request->getQueryParam( 'u' );
		if ( $userId ) {
			return $this->viewUser( $response, $contest, $userId );
		}

		$scores = Score::where( 'contest_id', $contest->id )
			->select(
				'users.name AS username',
				'user_id',
				Manager::raw( 'SUM(points) AS points' ),
				Manager::raw( 'SUM(contributions) AS contributions' ),
				Manager::raw( 'SUM(validations) AS validations' )
			)
			->join( 'users', 'users.id', '=', 'user_id' )
			->groupBy( 'user_id' )
			->orderBy(
				Manager::raw( 'SUM(points)' ),
				Manager::raw( 'SUM(contributions)' ),
				Manager::raw( 'SUM(validations)' )
			)
			->get();
		return $this->renderView( $response, 'contests_view.html.twig', [
			'contest' => $contest,
			'scores' => $scores,
			'can_edit' => ( $contest->hasAdmin( $_SESSION['username'] )->count() > 0 ),
		] );
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 * @param string[] $args
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function edit( Request $request, Response $response, $args ) {
		if ( !isset( $_SESSION['username'] ) ) {
			return $response->withRedirect( $this->router->urlFor( 'login' ) );
		}

		$id = $request->getAttribute( 'id' );
		if ( $id ) {
			$contestQuery = $this->db->prepare( 'SELECT c.id, c.name, c.start_date, c.end_date
            FROM contests c
              JOIN admins a ON a.contest_id = c.id
              JOIN users u ON a.user_id = u.id
            WHERE c.id = :id AND u.name = :username
            GROUP BY c.id
            ' );
			$contestQuery->execute( [ 'id' => $id, 'username' => $_SESSION['username'] ] );
			$contest = $contestQuery->fetch();
			$id = $contest['id'];

			// Admins.
			$adminsQuery = $this->db->prepare( 'SELECT u.name
            FROM users u
              JOIN admins a ON a.user_id=u.id
              JOIN contests c ON a.contest_id=c.id
            WHERE c.id = :id
            ' );
			$adminsQuery->execute( [ 'id' => $id ] );
			$admins = '';
			foreach ( $adminsQuery->fetchAll() as $admin ) {
				$admins .= "\n" . $admin['name'];
			}

			// Excluded users.
			$excludedUsersQuery = $this->db->prepare( 'SELECT u.name
            FROM users u
              JOIN excluded_users eu ON eu.user_id=u.id
              JOIN contests c ON eu.contest_id=c.id
            WHERE c.id = :id
            ' );
			$excludedUsersQuery->execute( [ 'id' => $id ] );
			$excludedUsers = '';
			foreach ( $excludedUsersQuery->fetchAll() as $excludedUser ) {
				$excludedUsers .= "\n" . $excludedUser['name'];
			}

			// Index pages.
			$indexPagesQuery = $this->db->prepare( 'SELECT url '
				. ' FROM index_pages ip '
				. '   JOIN contest_index_pages cip ON cip.index_page_id = ip.id '
				. ' WHERE contest_id = :id'
			);
			$indexPagesQuery->execute( [ 'id' => $id ] );
			$indexPages = '';
			foreach ( $indexPagesQuery->fetchAll() as $indexPage ) {
				$indexPages .= $indexPage['url'] . "\n";
			}

		}

		if ( !$id ) {
			$contest = new Contest();
			$admins = $_SESSION['username'];
			$excludedUsers = '';
			$indexPages = '';
		}

		return $this->renderView( $response, 'contests_edit.html.twig', [
			'contest' => $contest,
			'admins' => $admins,
			'index_pages' => $indexPages,
			'excluded_users' => $excludedUsers,
		] );
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 * @param string[] $args
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function save( Request $request, Response $response, $args ) {
		if ( !isset( $_SESSION['username'] ) ) {
			return $response->withRedirect( $this->router->urlFor( 'login' ) );
		}

		$this->db->beginTransaction();

		// Get the contest, and give up if it doesn't exist.
		// @TODO Check authorisation
		$contest = Contest::firstOrNew( [ 'id' => $request->getParam( 'id' ) ] );

		$contest->name = $request->getParam( 'name' );
		$contest->start_date = $request->getParam( 'start_date' );
		$contest->end_date = $request->getParam( 'end_date' );
		try {
			$contest->save();
		} catch ( QueryException $exception ) {
			$this->setFlash( 'unable-to-save', 'error', [ $exception->getMessage() ] );
			return $this->renderView( $response, 'contests_edit.html.twig', [
				'contest' => $contest,
				'admins' => $request->getParam( 'admins' ),
				'index_pages' => $request->getParam( 'index_pages' ),
				'excluded_users' => $request->getParam( 'excluded_users' ),
			] );
		}

		// Save admins.
		$admins = Str::explode( $request->getParam( 'admins' ) );
		if ( !in_array( $_SESSION['username'], $admins ) ) {
			// Make sure the current user is always an admin, so they can't lock themselves out.
			$admins[] = $_SESSION['username'];
		}
		$adminUserIds = [];
		foreach ( $admins as $admin ) {
			$adminUserIds[] = User::firstOrCreate( [ 'name' => $admin ] )->id;
		}
		$contest->admins()->sync( $adminUserIds );

		// Excluded users.
		$excludedUserIds = [];
		$excludedUsers = Str::explode( $request->getParam( 'excluded_users' ) );
		foreach ( $excludedUsers as $excludedUser ) {
			$excludedUserIds[] = User::firstOrCreate( [ 'name' => $excludedUser ] )->id;
		}
		$contest->excludedUsers()->sync( $excludedUserIds );

		// Index pages.
		$indexPageUrls = Str::explode( $request->getParam( 'index_pages' ) );
		$indexPageIds = [];
		foreach ( $indexPageUrls as $indexPageUrl ) {
			$indexPageIds[] = IndexPage::firstOrCreate( [ 'url' => $indexPageUrl ] )->id;
		}
		$contest->indexPages()->sync( $indexPageIds );

		$this->db->commit();
		return $response->withRedirect(
			$this->router->urlFor( 'contests_view', [ 'id' => $contest->id ] )
		);
	}
}
