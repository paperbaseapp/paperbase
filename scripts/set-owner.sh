#!/bin/sh

# Synopsis:
# set-library-owner.sh <library directory> <owner uid>

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Exactly two paramters are required"
fi

chown -R "$2":www-data "$1"
chmod -R g+rwX "$1"
