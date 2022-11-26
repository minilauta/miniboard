#!/bin/sh

kubectl apply -f k8s/secrets.yml
kubectl apply -f k8s/volumes.yml
kubectl apply -f k8s/services.yml
kubectl apply -f k8s/deployments.yml
kubectl apply -f k8s/pods.yml
