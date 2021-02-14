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
  echo "Starting $(nproc --all) workers..."

  for i in $(seq $(nproc --all)); do
    run_as_www_data php artisan queue:listen --tries=3 --backoff=5 --timeout=600 -vvv &
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

  run_as_www_data php artisan migrate --force
  exec apache2-foreground
fi
