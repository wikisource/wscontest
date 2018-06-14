<?php

namespace Wikisource\WsContest\Controller;

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthController extends Controller {

	protected $oauthClient;

	public function __construct( ContainerInterface $container ) {
		parent::__construct( $container );
		$conf = new ClientConfig( 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth' );
		$consumer = new Consumer( $this->container['settings']['oauthToken'], $this->container['settings']['oauthSecret'] );
		$conf->setConsumer( $consumer );
		$this->oauthClient = new Client( $conf );
	}

	public function login( Request $request, Response $response, $args ) {
		list( $next, $token ) = $this->oauthClient->initiate();
		$_SESSION['wscontests_oauth_token'] = $token->key;
		$_SESSION['wscontests_oauth_secret'] = $token->secret;
		return $response->withRedirect( $next );
	}

	public function callback( Request $request, Response $response, $args ) {
		if ( !empty( $_SESSION['username'] ) ) {
			$this->setFlash( "Already logged in", 'success' );
			return $response->withRedirect( $this->router->urlFor( 'home' ) );
		}
		if ( empty( $_SESSION['wscontests_oauth_token'] ) || empty( $_SESSION['wscontests_oauth_secret'] ) ) {
			return $response->withRedirect( $this->router->urlFor( 'login' ) );
		}
		$verifyCode = $request->getQueryParam( 'oauth_verifier' );
		if ( !$verifyCode ) {
			return $response->withRedirect( $this->router->urlFor( 'login' ) );
		}
		$requestToken = new Token( $_SESSION['wscontests_oauth_token'], $_SESSION['wscontests_oauth_secret'] );
		$accessToken = $this->oauthClient->complete( $requestToken, $verifyCode );
		$ident = $this->oauthClient->identify( $accessToken );
		unset( $_SESSION['wscontests_oauth_token'], $_SESSION['wscontests_oauth_secret'] );
		$_SESSION['username'] = $ident->username;
		return $response->withRedirect( $this->router->urlFor( 'home' ) );
	}

	public function logout( Request $request, Response $response, $args ) {
		session_destroy();
		return $response->withRedirect( $this->router->urlFor( 'home' ) );
	}
}
