<?php
// Autoload Twig and IntlExtension
require_once __DIR__ . '/vendor/autoload.php';  // Adjust this path as per your directory structure

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\Extra\Intl\IntlExtension;

// Set up Twig environment
$loader = new FilesystemLoader(__DIR__ . '/src/templates');  // Adjust this path to your templates directory
$twig = new Environment($loader);
$twig->addExtension(new IntlExtension());

// Example date to render
$date = new \DateTime('now', new \DateTimeZone('UTC'));

// Render template
echo $twig->render('templates/contests_view.html.twig', ['date' => $date]);
