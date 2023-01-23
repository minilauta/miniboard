#!/bin/bash

# for microk8s kubectl alias
if [[ -f ~/.bash_aliases ]]; then
    shopt -s expand_aliases
    source ~/.bash_aliases
fi

kubectl rollout restart deployment miniboard-app        --namespace=miniboard
kubectl rollout restart deployment miniboard-nginx      --namespace=miniboard
kubectl rollout restart deployment miniboard-redis      --namespace=miniboard
kubectl rollout restart deployment miniboard-mariadb    --namespace=miniboard
