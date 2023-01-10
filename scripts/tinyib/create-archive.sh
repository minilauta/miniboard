#!/bin/bash

#
# packs TinyIB archives to be unpacked by 'unpack-archive.sh'
# arg1: base path to archive from
# arg2: comma separated list of subfolders at base path (boards for example)
#

for i in ${2//,/ }
do
    tar -zcvf "tinyib_${i}_thumb.tar.gz" -C "$1/$i/thumb/" .
    tar -zcvf "tinyib_${i}_src.tar.gz" -C "$1/$i/src/" .
done
