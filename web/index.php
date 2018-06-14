<?php

use Wikisource\WsContest\Controller\AuthController;
use Wikisource\WsContest\Controller\ContestsController;
use Wikisource\WsContest\Controller\HomeController;

require_once __DIR__ . '/../bootstrap.php';

session_start();

// Routes.
$app->get( '/', HomeController::class.':home' )->setName( 'home' );
$app->get( '/login', AuthController::class.':login' )->setName( 'login' );
$app->get( '/login/callback', AuthController::class.':callback' )->setName( 'oauthcallback' );
$app->get( '/logout', AuthController::class.':logout' )->setName( 'logout' );

$app->get( '/c', ContestsController::class.':index' )->setName( 'contests' );
$app->get( '/c/new', ContestsController::class.':edit' )->setName( 'contests_create' );
$app->post( '/c/save', ContestsController::class.':save' )->setName( 'contests_save' );
$app->get( '/c/{id:[0-9]+}', ContestsController::class.':view' )->setName( 'contests_view' );
$app->get( '/c/{id:[0-9]+}/edit', ContestsController::class.':edit' )->setName( 'contests_edit' );

// Run the application.
$app->run();
