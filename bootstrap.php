<?php

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Illuminate\Database\Capsule\Manager;
use Krinkle\Intuition\Intuition;
use Slim\App;
use Slim\Container;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Twig\Extension\DebugExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

// Set up configuration.
if ( substr( basename( $_SERVER['PHP_SELF'] ), 0, 7 ) === 'phpunit' ) {
	$configFilename = __DIR__ . '/tests/config.php';
} else {
	$configFilename = __DIR__ . '/config.php';
}
if ( file_exists( $configFilename ) ) {
	/**
	 * @param string $configFilename
	 * @return string[]
	 */
	function getConfig( $configFilename ) {
		require_once $configFilename;
		$reqVars = [ 'dbHost', 'dbName', 'dbUser', 'dbPass', 'oauthToken', 'oauthSecret' ];
		foreach ( $reqVars as $reqVar ) {
			if ( !isset( $$reqVar ) ) {
				echo "Please set $$reqVar in $configFilename";
				exit( 1 );
			}
		}
		unset( $configFilename, $reqVars );
		return get_defined_vars();
	}

	$config = getConfig( $configFilename );
} else {
	echo "Please create $configFilename\n";
	exit();
}
$configDefaults = [
	'varDir' => __DIR__ . '/var',
];
$config = array_merge( ( isset( $config ) ? $config : [] ), $configDefaults );
$config['displayErrorDetails'] = ( isset( $config['debug'] ) && $config['debug'] );

// Set up the application.
$app = new App( [ 'settings' => $config ] );
$container = $app->getContainer();

// Internationalisation.
$container['lang'] = function ( Container $container ) {
	$int = new Intuition( 'wscontests' );
	$int->registerDomain( 'wscontests', __DIR__ . '/lang' );
	return $int;
};

// Views.
$container['view'] = function ( Container $container ) {
	if ( $container->get( 'settings' )->get( 'displayErrorDetails' ) ) {
		$twigOptions = [
			'debug' => true,
			'cache' => false,
		];
	} else {
		$twigOptions = [
			'debug' => false,
			'cache' => $container['settings']['varDir'] . '/twig'
		];
	}
	$view = new Twig( __DIR__ . '/tpl', $twigOptions );
	$view->getEnvironment()->addGlobal( 'debug', $container['settings']['debug'] );
	if ( $container['settings']['debug'] ) {
		$view->addExtension( new DebugExtension() );
	}
	$basePath = rtrim(
		str_ireplace(
			'index.php', '', $container['request']->getUri()->getBasePath()
		),
		'/'
	);
	$view->addExtension( new TwigExtension( $container['router'], $basePath ) );

	// Add filters.
	$view->getEnvironment()->addFilter(
		new TwigFilter( 'wordwrap', function ( $str, $width = 75 ) {
			return wordwrap( $str, $width );
		} )
	);

	$view->getEnvironment()->addFunction(
		new TwigFunction( 'msg', function ( $msg, $vars = [] ) use ( $container ) {
			return $container['lang']->msg( $msg, [ 'variables' => $vars ] );
		} )
	);

	$view->getEnvironment()->addFunction(
		new TwigFunction( 'query_log',
		function () use ( $container ) {
			return Manager::getQueryLog();
		} )
	);

	return $view;
};

// Database.
$container['db'] = function ( Container $container ) {
	$config = new Configuration();
	$url = "mysql://{$container['settings']['dbHost']};dbname={$container['settings']['dbName']}";
	$connectionParams = [
		'driver' => 'pdo_mysql',
		'url' => $url,
		'port' => $container['settings']['dbPort'],
		'user' => $container['settings']['dbUser'],
		'password' => $container['settings']['dbPass'],
	];
	$conn = DriverManager::getConnection( $connectionParams, $config );
	$conn->exec( 'SET NAMES utf8mb4' );
	return $conn;
};

$capsule = new Manager();
$capsule->addConnection( [
	'driver' => 'mysql',
	'database' => $container['settings']['dbName'],
	'host' => $container['settings']['dbHost'],
	'port' => $container['settings']['dbPort'],
	'username' => $container['settings']['dbUser'],
	'password' => $container['settings']['dbPass'],
	'charset' => 'utf8mb4',
	'collation' => 'utf8mb4_unicode_ci',
] );
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule->getConnection()->enableQueryLog();
