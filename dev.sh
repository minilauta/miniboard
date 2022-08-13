#!/bin/bash

docker-compose up --force-recreate & sudo php -S localhost:80 -t public
docker-compose down
