<?php

namespace App\Controller;

use App\Repository\ContestRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController {

	/**
	 * @param ContestRepository $contestRepository
	 * @param UserRepository $userRepository
	 * @return Response
	 */
	#[Route( "/", name:"home" )]
	public function home( ContestRepository $contestRepository, UserRepository $userRepository ): Response {
		return $this->render( 'home.html.twig', [
			'contests' => (string)$contestRepository->count(),
			'people' => (string)$userRepository->count(),
		] );
	}
}
