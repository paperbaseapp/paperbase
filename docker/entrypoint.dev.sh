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
  while true; do # This makes sure the worker always has the latest code
    run_as_www_data php artisan queue:work --tries=3 --delay=10 -vvv --stop-when-empty
    sleep 1
  done
elif [ "$1" = "scheduler" ]; then
  wait-for-it app:80 -t 3600
  exec_as_www_data php artisan cron --interval 10 -q
else
  composer install

  chgrp -R www-data /app/storage
  chmod -R g+rw /app/storage

  wait-for-it -t 60 db:5432

  run_as_www_data php artisan migrate --force
  exec apache2-foreground
fi
