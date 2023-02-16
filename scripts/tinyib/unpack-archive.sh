#!/bin/bash

#
# unpacks TinyIB archives packed by 'create-archive.sh'
# arg1: base path to unpack into
# arg2: comma separated list of archive tags (boards for example)
#

for i in ${2//,/ }
do
    tar -xvzf "tinyib_${i}_thumb.tar.gz" --transform="s/.*\///" -C "$1"
    tar -xvzf "tinyib_${i}_src.tar.gz" --transform="s/.*\///" -C "$1"
done
