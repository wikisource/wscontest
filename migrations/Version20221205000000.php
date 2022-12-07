<?php

declare( strict_types=1 );

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221205000000 extends AbstractMigration {

	/**
	 * @return string
	 */
	public function getDescription(): string {
		return 'Add contests.privacy column';
	}

	/**
	 * @param Schema $schema
	 * @return void
	 */
	public function up( Schema $schema ): void {
		$this->addSql( 'ALTER TABLE contests ADD COLUMN privacy INT(1) UNSIGNED NOT NULL DEFAULT "1" AFTER name' );
	}

	/**
	 * @param Schema $schema
	 * @return void
	 */
	public function down( Schema $schema ): void {
		$this->addSql( 'ALTER TABLE contests DROP COLUMN privacy' );
	}
}
