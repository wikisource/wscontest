<?php

namespace Wikisource\WsContest\Controller;

use Wikisource\WsContest\Entity\Contest;
use Wikisource\WsContest\Entity\User;

class HomeController extends Controller {

	public function home( $request, $response, $args ) {
		return $this->renderView( $response, 'home.html.twig', [
			'contests' => Contest::all()->count(),
			'people' => User::all()->count(),
		] );
	}

}
