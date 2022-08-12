<?php

namespace App\Controller;

use App\Repository\ContestRepository;
use App\Repository\IndexPageRepository;
use App\Repository\UserRepository;
use App\Str;
use DateInterval;
use DateTime;
use DateTimeZone;
use Krinkle\Intuition\Intuition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Routing\Annotation\Route;

class ContestsController extends AbstractController {

	/**
	 * @param Session $session
	 * @return string|null
	 */
	private function getLoggedInUsername( Session $session ): ?string {
		return $session->has( 'logged_in_user' )
			? $session->get( 'logged_in_user' )->username
			: null;
	}

	/**
	 * phpcs:ignore MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
	 * @Route("/c", name="contests")
	 * @param Session $session
	 * @param ContestRepository $contestRepository
	 * @return Response
	 */
	public function index( Session $session, ContestRepository $contestRepository ): Response {
		$username = $this->getLoggedInUsername( $session );
		if ( !$username ) {
			$this->addFlash( 'warning', [ 'not-logged-in', [ 'foo' ] ] );
		}
		return $this->render( 'contests.html.twig', [
			'username' => $username,
			'contests' => $username ? $contestRepository->getContestsForUser( $username ) : null,
		] );
	}

	/**
	 * phpcs:ignore MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
	 * @Route("/c/{id}", name="contests_view", requirements={"id"="\d+"})
	 * phpcs:ignore MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
	 * @Route("/c/{id}.{format}", name="contests_view", requirements={"id"="\d+"})
	 * @param ContestRepository $contestRepository
	 * @param UserRepository $userRepository
	 * @param Request $request
	 * @param Session $session
	 * @param Intuition $intuition
	 * @param int $scoreCalculationInterval
	 * @param string $id
	 * @param ?string $format
	 * @return Response
	 */
	public function view(
		ContestRepository $contestRepository,
		UserRepository $userRepository,
		Request $request,
		Session $session,
		Intuition $intuition,
		int $scoreCalculationInterval,
		string $id,
		?string $format = 'html'
	): Response {
		// Find the contest.
		$contest = $contestRepository->get( $id );
		if ( !$contest ) {
			throw $this->createNotFoundException( $intuition->msg( 'contest-not-found' ) );
		}

		// If a user ID is requested, show only the scores for that user.
		$userId = $request->get( 'u' );
		if ( $userId ) {
			return $this->render( 'contests_viewuser.html.twig', [
				'contest' => $contest,
				'scores' => $contestRepository->getScoresForUser( $userId, $contest['id'] ),
				'user' => $userRepository->get( $userId ),
			] );
		}

		$username = $this->getLoggedInUsername( $session );
		$canEdit = $username && $contestRepository->hasAdmin( $id, $username );
		$response = new Response();
		if ( $format === 'wikitext' ) {
			$response->headers->set( 'Content-Type', 'text/plain' );
		}
		$content = $this->renderView( "contests_view.$format.twig", [
			'contest' => $contest,
			'scores' => $contestRepository->getscores( $id ),
			'can_edit' => $canEdit,
			'can_view_scores' => $canEdit || !$contest['in_progress'],
			'score_calculation_interval' => $scoreCalculationInterval,
		] );
		$response->setContent( $content );
		return $response;
	}

	/**
	 * phpcs:ignore MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
	 * @Route("/c/new", name="contests_create")
	 * phpcs:ignore MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
	 * @Route("/c/{id}/edit", name="contests_edit", requirements={"id"="\d+"})
	 * @param Session $session
	 * @param ContestRepository $contestRepository
	 * @param int $scoreCalculationInterval
	 * @param ?string $id
	 * @return Response
	 */
	public function edit(
		Session $session, ContestRepository $contestRepository,
		int $scoreCalculationInterval,
		?string $id = null
	): Response {
		$username = $this->getLoggedInUsername( $session );
		if ( !$username ) {
			throw $this->createAccessDeniedException();
		}

		$indexPages = '';
		$excludedUsers = '';
		$admins = '';
		if ( $id ) {
			$contest = $contestRepository->get( $id );
			$isAdmin = false;
			foreach ( $contest['admins'] as $admin ) {
				$admins .= $admin['name'] . "\n";
				$isAdmin = $isAdmin || $admin['name'] === $username;
			}
			if ( !$isAdmin ) {
				throw $this->createAccessDeniedException();
			}
			foreach ( $contest['excluded_users'] as $excludedUser ) {
				$excludedUsers .= $excludedUser['name'] . "\n";
			}
			foreach ( $contest['index_pages'] as $indexPage ) {
				$indexPages .= $indexPage['url'] . "\n";
			}
		} else {
			$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			$twoWeeks = new DateInterval( 'P14D' );
			$contest = [
				'id' => false,
				'name' => '',
				'start_date' => $now->format( 'Y-m-d 00:00:01' ),
				'end_date' => $now->add( $twoWeeks )->format( 'Y-m-d 23:59:59' ),
			];
			$admins = $username;
		}

		return $this->render( 'contests_edit.html.twig', [
			'contest' => $contest,
			'admins' => $admins,
			'index_pages' => $indexPages,
			'excluded_users' => $excludedUsers,
			'score_calculation_interval' => $scoreCalculationInterval,
		] );
	}

	/**
	 * phpcs:ignore MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
	 * @Route( "/c/save", name="contests_save" )
	 * @param Session $session
	 * @param Request $request
	 * @param ContestRepository $contestRepository
	 * @param IndexPageRepository $indexPageRepository
	 * @return Response
	 */
	public function save(
		Session $session,
		Request $request,
		ContestRepository $contestRepository,
		IndexPageRepository $indexPageRepository
	): Response {
		$username = $this->getLoggedInUsername( $session );
		if ( !$username ) {
			throw new AccessDeniedHttpException();
		}
		if ( !$this->isCsrfTokenValid( 'contest-edit', $request->request->get( 'csrf_token' ) ) ) {
			throw new AccessDeniedHttpException();
		}

		// Get the contest, and check if the user is an admin.
		$id = (string)$request->request->get( 'id', '' );
		if ( $id ) {
			$contest = $contestRepository->get( $id );
			if ( $contest && !$contestRepository->hasAdmin( $id, $username ) ) {
				throw new AccessDeniedHttpException();
			}
		}

		$admins = array_filter( Str::explode( $request->request->get( 'admins', '' ) ) );
		if ( !in_array( $username, $admins ) ) {
			// Make sure the current user is always an admin, so they can't lock themselves out.
			$admins[] = $username;
		}

		$indexPageUrls = Str::explode( $request->request->get( 'index_pages' ) );
		$indexPageResult = $indexPageRepository->saveUrls( $indexPageUrls );
		foreach ( $indexPageResult['warnings'] as $warning ) {
			$this->addFlash( 'warning', $warning );
		}

		$id = $contestRepository->save(
			$id,
			$request->request->get( 'name' ),
			$request->request->get( 'start_date' ),
			$request->request->get( 'end_date' ),
			$admins,
			Str::explode( $request->request->get( 'excluded_users' ) ),
			$indexPageUrls
		);

		// Reset scores, to ensure they'll be re-calculated.
		$contestRepository->deleteScores( $id );

		return $this->redirectToRoute( 'contests_view', [ 'id' => $id ] );
	}
}
