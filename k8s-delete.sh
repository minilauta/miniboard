#!/bin/bash

# for microk8s kubectl alias
shopt -s expand_aliases
source ~/.bash_aliases

kubectl delete -f k8s/ingress.yml
kubectl delete -f k8s/pods.yml
kubectl delete -f k8s/deployments.yml
kubectl delete -f k8s/services.yml
kubectl delete -f k8s/volumes.yml
kubectl delete -f k8s/secrets.yml
