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

# K8s ingress

To have k8s nginx ingress controller properly pass `X-Forwarded-For` (or any other headers) to miniboard, you must manually configure your k8s cluster's ingress controller's ConfigMap.

## Microk8s example

### ConfigMap
```yaml
apiVersion: v1
data:
  use-forwarded-headers: "true"
kind: ConfigMap
metadata:
  annotations:
    kubectl.kubernetes.io/last-applied-configuration: |
      {"apiVersion":"v1","kind":"ConfigMap","metadata":{"annotations":{},"name":"nginx-load-balancer-microk8s-conf","namespace":"ingress"}}
  creationTimestamp: "2023-02-07T11:48:02Z"
  name: nginx-load-balancer-microk8s-conf
  namespace: ingress
  resourceVersion: "1339513"
  uid: e854fffa-e8b8-43f9-941b-6c8f0a8304e1
```

### Commands
`$ kubectl -n ingress get cm`  
`$ KUBE_EDITOR="nano" kubectl -n ingress edit configmaps nginx-load-balancer-microk8s-conf`  
`$ kubectl -n ingress get pods`  
`$ kubectl -n ingress logs nginx-ingress-microk8s-controller-mftjp | grep reload`  

# K8s volumes

By default, the persistent volumes created by the claims have retain policy set to `Delete`. To change this, manually patch the volumes using kubectl.

### Commands

`$ kubectl get pv`  
`$ kubectl patch pv <your-pv-name> -p '{"spec":{"persistentVolumeReclaimPolicy":"Retain"}}'`  

# Screenshots

![Example screenshot](/.docs/screenshot.png "Example screenshot")
*As you can see, quite a few features are yet to be implemented...*
