#!/bin/bash

# for microk8s kubectl alias
if [[ -f ~/.bash_aliases ]]; then
    shopt -s expand_aliases
    source ~/.bash_aliases
fi

kubectl delete pod pod-miniboard-flyway --namespace=miniboard
