FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates mariadb-server mariadb-client \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh && chown -R www-data:www-data /var/www/html/storage

# Pre-initialize MariaDB database at build time so container startup in Render is instantaneous!
RUN mkdir -p /run/mysqld && chown -R mysql:mysql /run/mysqld /var/lib/mysql && \
    mariadbd --user=mysql --bind-address=127.0.0.1 --port=3306 --socket=/run/mysqld/mysqld.sock --skip-grant-tables & \
    for i in $(seq 1 30); do if mariadb-admin --socket=/run/mysqld/mysqld.sock ping --silent 2>/dev/null; then break; fi; sleep 0.3; done && \
    mariadb --socket=/run/mysqld/mysqld.sock -u root < /var/www/html/database/instalacion_completa_iestpu.sql && \
    mariadb-admin --socket=/run/mysqld/mysqld.sock shutdown

EXPOSE 80
CMD ["/usr/local/bin/docker-entrypoint.sh"]
