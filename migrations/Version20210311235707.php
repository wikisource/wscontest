<?php

declare( strict_types=1 );

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210311235707 extends AbstractMigration {

	/**
	 * @return string
	 */
	public function getDescription(): string {
		return 'Initial install.';
	}

	/**
	 * @param Schema $schema
	 * @return void
	 */
	public function up( Schema $schema ): void {
		$this->addSql( 'CREATE TABLE IF NOT EXISTS contests (
			id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			name VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL UNIQUE,
			start_date DATETIME NOT NULL,
			end_date DATETIME NOT NULL
		) DEFAULT CHARSET=utf8mb4;' );

		$this->addSql( 'CREATE TABLE IF NOT EXISTS users (
			id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			name VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL UNIQUE
		) DEFAULT CHARSET=utf8mb4;' );

		$this->addSql( 'CREATE TABLE IF NOT EXISTS admins (
			contest_id INT(10) UNSIGNED NOT NULL,
			user_id INT(10) UNSIGNED NOT NULL,
			CONSTRAINT contest_admins_pk PRIMARY KEY (contest_id, user_id),
			CONSTRAINT contest_admins_contest_fk FOREIGN KEY (contest_id) REFERENCES contests (id),
			CONSTRAINT contest_admins_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
		) DEFAULT CHARSET=utf8mb4;' );

		$this->addSql( 'CREATE TABLE IF NOT EXISTS excluded_users (
			contest_id INT(10) UNSIGNED NOT NULL,
			user_id INT(10) UNSIGNED NOT NULL,
			CONSTRAINT excluded_users_pk PRIMARY KEY (contest_id, user_id),
			CONSTRAINT excluded_users_contest_fk FOREIGN KEY (contest_id) REFERENCES contests (id),
			CONSTRAINT excluded_users_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
		) DEFAULT CHARSET=utf8mb4;' );

		$this->addSql( 'CREATE TABLE IF NOT EXISTS index_pages (
			id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			url VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL UNIQUE
		) DEFAULT CHARSET=utf8mb4;' );

		$this->addSql( 'CREATE TABLE IF NOT EXISTS contest_index_pages (
			contest_id INT(10) UNSIGNED NOT NULL,
			index_page_id INT(10) UNSIGNED NOT NULL,
			CONSTRAINT contest_index_pages_pk PRIMARY KEY (contest_id, index_page_id),
			CONSTRAINT contest_index_pages_contest_fk
				FOREIGN KEY (contest_id) REFERENCES contests (id),
			CONSTRAINT contest_index_pages_index_page_fk
				FOREIGN KEY (index_page_id) REFERENCES index_pages (id)
		) DEFAULT CHARSET=utf8mb4;' );

		$this->addSql( 'CREATE TABLE IF NOT EXISTS scores (
			id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			index_page_id INT(10) UNSIGNED NOT NULL,
			contest_id INT(10) UNSIGNED NOT NULL,
			user_id INT(10) UNSIGNED NOT NULL,
			revision_id INT(10) UNSIGNED NOT NULL,
			revision_datetime DATETIME NOT NULL,
			points INT(5) NULL DEFAULT NULL,
			validations INT(5) NULL DEFAULT NULL,
			contributions INT(5) NULL DEFAULT NULL,
			CONSTRAINT scores_index_page_fk FOREIGN KEY (index_page_id) REFERENCES index_pages (id),
			CONSTRAINT scores_contest_fk FOREIGN KEY (contest_id) REFERENCES contests (id),
			CONSTRAINT scores_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
		) DEFAULT CHARSET=utf8mb4;' );
	}

	/**
	 * @param Schema $schema
	 * @return void
	 */
	public function down( Schema $schema ): void {
		$this->addSql( 'DROP TABLE admins' );
		$this->addSql( 'DROP TABLE contest_index_pages' );
		$this->addSql( 'DROP TABLE contests' );
		$this->addSql( 'DROP TABLE excluded_users' );
		$this->addSql( 'DROP TABLE index_pages' );
		$this->addSql( 'DROP TABLE scores' );
		$this->addSql( 'DROP TABLE users' );
	}
}
