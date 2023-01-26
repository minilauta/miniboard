# miniboard

Minimalistic imageboard software, written in PHP 8 with the help of Slim 4 micro framework.

# NOTE

This software is currently a work in progress. Breaking changes are to be expected. For example, the SQL migrations are still being changed in-place, instead of using incremental migrations properly. This is because we want the baseline database schema to be sensible and optimal.

# Dev commands

## Install deps
`$ yarn`  
`$ composer install`

## Develop locally
`$ docker compose up --build -d && yarn start`

## Run tests
`$ docker compose build && docker compose run test`

# K8s scripts

## Deploy
`$ ./scripts/k8s/apply.sh`

## Update
`$ ./scripts/k8s/rollout.sh`  
`$ ./scripts/k8s/migrate.sh`

## Destroy
`$ ./scripts/k8s/delete.sh`

# Screenshots

![Example screenshot](/.docs/screenshot.png "Example screenshot")
*As you can see, quite a few features are yet to be implemented...*
