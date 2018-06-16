<?php

namespace Wikisource\WsContest\Controller;

use Slim\Http\Request;
use Slim\Http\Response;
use Wikisource\WsContest\Entity\Contest;
use Wikisource\WsContest\Entity\User;

class HomeController extends Controller {

	/**
	 * @param Request $request
	 * @param Response $response
	 * @param string $args
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function home( Request $request, Response $response, $args ) {
		return $this->renderView( $response, 'home.html.twig', [
			'contests' => Contest::all()->count(),
			'people' => User::all()->count(),
		] );
	}

}
