#!/bin/bash

composer install --no-interaction --no-scripts
bin/console assets:install

if [ "$DATABASE"="postgres" ]
then
    echo "Waiting for postgreSQL..."

    while ! nc -z database $POSTGRES_PORT; do
        sleep 0.1
    done

    echo "PostgreSQL started"
fi

exec "$@"