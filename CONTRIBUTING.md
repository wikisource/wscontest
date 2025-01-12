# Contributing to wscontest

The development of this software is covered by a [Code of Conduct](https://www.mediawiki.org/wiki/Special:MyLanguage/Code_of_Conduct).

## Prerequesites

* PHP
* MariaDB or MySQL
* [Composer](https://getcomposer.org/)
* [Symfony CLI](https://symfony.com/download#step-1-install-symfony-cli)

## Installation for development

1. Clone repo: `git clone https://github.com/wikisource/wscontest`
2. `cd wscontest`
3. Update dependencies: `composer install`
4. Copy `.env` to `.env.local` and add your database and Oauth credentials
5. Create the database: `./bin/console doctrine:database:create`
6. Create the database structure: `./bin/console doctrine:migrations:migrate`
7. Start a local web server: `symfony server:start --daemon`
8. Browse to http://localhost:8000 (or whichever address the server starts at)
