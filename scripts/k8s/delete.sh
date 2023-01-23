#!/bin/bash

# for microk8s kubectl alias
if [[ -f ~/.bash_aliases ]]; then
    shopt -s expand_aliases
    source ~/.bash_aliases
fi

echo "WARNING: This will destroy everything!"

read -p "Continue (y/n): " CONTINUE
if [[ "$CONTINUE" = "y" ]]; then
    echo "Destroying..."

    kubectl delete -f k8s/ingress.yml       --namespace=miniboard
    kubectl delete -f k8s/pods.yml          --namespace=miniboard
    kubectl delete -f k8s/deployments.yml   --namespace=miniboard
    kubectl delete -f k8s/services.yml      --namespace=miniboard
    kubectl delete -f k8s/volumes.yml       --namespace=miniboard
    kubectl delete -f k8s/secrets.yml       --namespace=miniboard
else
    echo "Cancelled..."
fi
