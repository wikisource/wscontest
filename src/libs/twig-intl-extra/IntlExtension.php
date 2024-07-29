<?php
// Autoload Twig
require_once __DIR__ . '/vendor/twig/twig/lib/Twig/Autoloader.php';  // Adjust this path to where Twig is installed
Twig_Autoloader::register();

// Autoload IntlExtension
require_once __DIR__ . '/src/libs/twig-intl-extra/IntlExtension.php';  // Adjust this path as per your directory structure

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\Extra\Intl\IntlExtension;

// Set up Twig
$loader = new FilesystemLoader(__DIR__ . '/src/templates');  // Adjust this path to your templates directory
$twig = new Environment($loader);
$twig->addExtension(new IntlExtension());

// Example date to render
$date = new \DateTime('now', new \DateTimeZone('UTC'));

// Render template
echo $twig->render('your_template.html.twig', ['date' => $date]);
