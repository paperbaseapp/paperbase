#!/bin/sh

set -e

run_as_www_data() {
  su -c "$*" -s /bin/sh www-data
}

exec_as_www_data() {
  exec su -c "exec $*" -s /bin/sh www-data
}

if [ "$1" = "worker" ]; then
  echo "Waiting for app..."
  wait-for-it app:80 -t 3600
  echo "Starting $(nproc --all --ignore=1) workers..."
  for i in $(seq $(nproc --all --ignore=1)); do
    run_as_www_data php artisan queue:work --backoff=30 &
  done
  wait
elif [ "$1" = "scheduler" ]; then
  echo "Waiting for app..."
  wait-for-it app:80 -t 3600 || exit 1
  exec_as_www_data php artisan cron -q
else
  if [ ! -d /app/storage/app ]; then
    echo "Populating storage volume..."
    cp -vr /app/storage.dist/* /app/storage/
  fi

  echo "Changing storage permissions..."
  chgrp -R www-data /app/storage
  chmod -R g+rw /app/storage

  echo "Waiting for db..."
  wait-for-it -t 60 db:5432

  echo "Waiting for meilisearch..."
  wait-for-it -t 60 meilisearch:7700

  run_as_www_data php artisan migrate --force
  exec apache2-foreground
fi
