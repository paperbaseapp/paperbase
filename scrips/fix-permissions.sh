#!/bin/sh

chgrp -R www-data /app/storage/libraries/*
chmod -R g+rwX /app/storage/libraries/*
