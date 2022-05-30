<?php

namespace App\Controller;

use App\Repository\ContestRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController {

	/**
	 * phpcs:ignore MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
	 * @Route("/", name="home")
	 * @param ContestRepository $contestRepository
	 * @param UserRepository $userRepository
	 * @return Response
	 */
	public function home( ContestRepository $contestRepository, UserRepository $userRepository ): Response {
		return $this->render( 'home.html.twig', [
			'contests' => (string)$contestRepository->count(),
			'people' => (string)$userRepository->count(),
		] );
	}
}
