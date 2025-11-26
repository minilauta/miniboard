#!/bin/sh

php src/cli.php migrate
php -S 0.0.0.0:80 -t public
