<?php

namespace Wikisource\WsContest\Command;

use Doctrine\DBAL\Connection;
use Slim\App;
use Slim\Collection;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

abstract class Command extends SymfonyCommand {

	/** @var Connection */
	protected $db;

	/** @var Collection */
	protected $settings;

	/**
	 * @param App $slimApp
	 */
	public function setSlimApp( App $slimApp ) {
		$this->db = $slimApp->getContainer()->get( 'db' );
		$this->settings = $slimApp->getContainer()->get( 'settings' );
	}

}
