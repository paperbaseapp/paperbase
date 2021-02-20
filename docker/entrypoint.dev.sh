#!/bin/sh

set -e

run_as_www_data() {
  su -c "$*" -s /bin/sh www-data
}

exec_as_www_data() {
  exec su -c "exec $*" -s /bin/sh www-data
}

if [ "$1" = "worker" ]; then
  wait-for-it app:80 -t 3600
  echo "Starting $(nproc --all --ignore=1) workers..."
  for i in $(seq $(nproc --all --ignore=1)); do
    (
      while true; do
        run_as_www_data php artisan queue:work --backoff=5 --stop-when-empty --max-jobs=1 -vvv
      done
    ) &
  done
  wait
elif [ "$1" = "scheduler" ]; then
  wait-for-it app:80 -t 3600
  exec_as_www_data php artisan cron --interval 10
else
  composer install

  chgrp -R www-data /app/storage
  chmod -R g+rw /app/storage

  wait-for-it -t 60 db:5432
  wait-for-it -t 60 meilisearch:7700

  run_as_www_data php artisan migrate --force
  php artisan telescope:publish
  exec apache2-foreground
fi
