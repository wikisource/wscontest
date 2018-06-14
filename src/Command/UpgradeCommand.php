<?php

namespace Wikisource\WsContest\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeCommand extends Command
{

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('upgrade');
        $this->setDescription('Upgrade or install this application');
    }

    /**
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $sql = 'CREATE TABLE IF NOT EXISTS contests (
            id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(250) CHARACTER SET utf8mb4 NOT NULL UNIQUE,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL
        ) DEFAULT CHARSET=utf8mb4;';
        $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS users (
            id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(250) CHARACTER SET utf8mb4 NOT NULL UNIQUE
        ) DEFAULT CHARSET=utf8mb4;';
        $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS admins (
            contest_id INT(10) UNSIGNED NOT NULL,
            user_id INT(10) UNSIGNED NOT NULL,
            CONSTRAINT contest_admins_pk PRIMARY KEY (contest_id, user_id),
            CONSTRAINT contest_admins_contest_fk FOREIGN KEY (contest_id) REFERENCES contests (id),
            CONSTRAINT contest_admins_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
        ) DEFAULT CHARSET=utf8mb4;';
        $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS excluded_users (
            contest_id INT(10) UNSIGNED NOT NULL,
            user_id INT(10) UNSIGNED NOT NULL,
            CONSTRAINT excluded_users_pk PRIMARY KEY (contest_id, user_id),
            CONSTRAINT excluded_users_contest_fk FOREIGN KEY (contest_id) REFERENCES contests (id),
            CONSTRAINT excluded_users_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
        ) DEFAULT CHARSET=utf8mb4;';
        $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS index_pages (
            id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            url VARCHAR(250) CHARACTER SET utf8mb4 NOT NULL UNIQUE
        ) DEFAULT CHARSET=utf8mb4;';
        $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS contest_index_pages (
            contest_id INT(10) UNSIGNED NOT NULL,
            index_page_id INT(10) UNSIGNED NOT NULL,
            CONSTRAINT contest_index_pages_pk PRIMARY KEY (contest_id, index_page_id),
            CONSTRAINT contest_index_pages_contest_fk FOREIGN KEY (contest_id) REFERENCES contests (id),
            CONSTRAINT contest_index_pages_index_page_fk FOREIGN KEY (index_page_id) REFERENCES index_pages (id)
        ) DEFAULT CHARSET=utf8mb4;';
        $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS scores (
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
        ) DEFAULT CHARSET=utf8mb4;';
        $this->db->query($sql);
    }
}
