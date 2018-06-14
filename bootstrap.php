<?php

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Slim\App;
use Slim\Container;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

// Set up configuration.
if (substr(basename($_SERVER['PHP_SELF']), 0, 7)==='phpunit') {
    $configFilename = __DIR__ . '/tests/config.php';
} else {
    $configFilename = __DIR__ . '/config.php';
}
if (file_exists($configFilename)) {
    $config = (function () use ($configFilename) {
        require_once $configFilename;
        foreach (['dbHost', 'dbName', 'dbUser', 'dbPass', 'oauthToken', 'oauthSecret'] as $reqVar) {
            if (!isset($$reqVar)) {
                echo "Please set $$reqVar in $configFilename";
                exit(1);
            }
        }
        unset($configFilename);
        return get_defined_vars();
    })();
} else {
    echo 'Please create config.php';
    exit();
}
$configDefaults = [
    'varDir' => __DIR__ . '/var',
];
$config = array_merge((isset($config) ? $config : []), $configDefaults);

// Set up the application.
$app = new App(['settings' => $config]);
$container = $app->getContainer();

// Internationalisation.
$container['lang'] = function (Container $container) {
    $int = new Intuition( 'wscontests' );
    $int->registerDomain( 'wscontests', __DIR__ . '/lang' );
    return $int;
    //echo $int->msg( 'example' );
};

// Views.
$container['view'] = function (Container $container) {
    if ($container->get('settings')->get('displayErrorDetails')) {
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
    $view = new Twig(__DIR__ . '/tpl', $twigOptions);
    $view->addExtension(new Twig_Extension_Debug());
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new TwigExtension($container['router'], $basePath));

    // Add filters.
    $view->getEnvironment()->addFilter(new Twig_SimpleFilter('wordwrap', function ($str, $width = 75) {
        return wordwrap($str, $width);
    }));

	$view->getEnvironment()->addFunction(
		new Twig_SimpleFunction('msg', function ($msg, $vars = []) use ($container) {
			return $container['lang']->msg($msg, ['variables' => $vars ] );
		})
	);

	$view->getEnvironment()->addFunction(
		new Twig_SimpleFunction('query_log',
		function () use ($container) {
			return \Illuminate\Database\Capsule\Manager::getQueryLog();
		})
	);

	

    return $view;
};

//$container['session'] = function (Container $container) {
//  return new Session([
//      'name' => 'email_archiver',
//      'autorefresh' => true,
//      'lifetime' => '12 hour'
//  ]);
//};

// Database.
$container['db'] = function (Container $container) {
    $config = new Configuration();
    $connectionParams = [
        'driver' => 'pdo_mysql',
        'url' => 'mysql://' . $container['settings']['dbHost'] . ';dbname=' . $container['settings']['dbName'],
        'port' => $container['settings']['dbPort'],
        'user' => $container['settings']['dbUser'],
        'password' => $container['settings']['dbPass'],
    ];
    //var_dump($connectionParams);exit();
    $conn = DriverManager::getConnection($connectionParams, $config);
    $conn->exec('SET NAMES utf8mb4');
    return $conn;
};

//$container['orm'] = function (Container $container) {
	$capsule = new Illuminate\Database\Capsule\Manager();
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
//};
