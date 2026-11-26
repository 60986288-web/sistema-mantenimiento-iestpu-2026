#!/usr/bin/env bash
set -e

PORT_TO_USE="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT_TO_USE}/g" /etc/apache2/ports.conf
printf '<VirtualHost *:%s>\n    DocumentRoot /var/www/html/public\n    <Directory /var/www/html/public>\n        Options Indexes FollowSymLinks\n        AllowOverride All\n        Require all granted\n    </Directory>\n</VirtualHost>\n' "${PORT_TO_USE}" > /etc/apache2/sites-available/000-default.conf

# Start embedded MariaDB in background if no external DB_HOST is configured
if [ -z "${DB_HOST}" ] || [ "${DB_HOST}" = "127.0.0.1" ] || [ "${DB_HOST}" = "localhost" ]; then
    mkdir -p /run/mysqld && chown -R mysql:mysql /run/mysqld /var/lib/mysql
    mariadbd --user=mysql --bind-address=127.0.0.1 --port=3306 --socket=/run/mysqld/mysqld.sock --skip-grant-tables --innodb-buffer-pool-size=32M --performance-schema=OFF --skip-name-resolve &
    for i in $(seq 1 25); do
        if mariadb-admin --socket=/run/mysqld/mysqld.sock ping --silent 2>/dev/null; then break; fi
        sleep 0.2
    done
fi

exec apache2-foreground
