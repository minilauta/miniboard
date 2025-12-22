# miniboard

Minimalistic but modular imageboard software, written in PHP 8.x.

# Dev commands

## Develop locally
`$ docker compose up --build`

|addr|port|desc|
|-|-|-|
|localhost|9000|webpack dev server|
|localhost|8082|phpmyadmin|
|localhost|3306|mariadb|

# K8s scripts

The files under `./k8s/` provide an example for running this software in production. You don't have to run this within a k8s cluster, that's simply what I prefer to do because k8s is convenient and mostly autonomous.

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
