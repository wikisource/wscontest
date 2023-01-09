Wikisource Contest Tool
=======================

* Source code: https://github.com/wikisource/wscontest
* Production tool: [https://wscontest.toolforge.org/](https://wscontest.toolforge.org/)
* [![Build Status](https://travis-ci.org/wikisource/wscontest.svg?branch=master)](https://travis-ci.org/wikisource/wscontest)
* Issue tracker: https://phabricator.wikimedia.org/tag/tool-wscontest/
* Discussion: [https://meta.wikimedia.org/wiki/Talk:Wikisource_proofreading_contests](https://meta.wikimedia.org/wiki/Talk:Wikisource_proofreading_contests)

## Installation

1. Clone repo: `git clone https://github.com/wikisource/wscontest`
2. `cd wscontest`
3. Update dependencies: `composer install --no-dev`
4. Edit `config.php` to add your database and Oauth credentials
5. Set up the database: `./bin/wscontest upgrade`

## License

This is Free Software, released under the GNU General Public License (GPL) version 3.0 or later.
