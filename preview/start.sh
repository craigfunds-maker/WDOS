#!/bin/sh
set -e
PORT="${PORT:-10000}"
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
[ -d /var/lib/mysql/mysql ] || mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
mariadbd --user=mysql --innodb-buffer-pool-size=48M --performance-schema=0 --key-buffer-size=8M --max-connections=30 --skip-name-resolve --bind-address=127.0.0.1 &
i=0; until mariadb-admin ping --silent 2>/dev/null; do i=$((i+1)); [ $i -gt 60 ] && echo "database did not start" && exit 1; sleep 1; done
mariadb -e "CREATE DATABASE IF NOT EXISTS woddi CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS 'woddi'@'127.0.0.1' IDENTIFIED BY 'preview-only'; GRANT ALL ON woddi.* TO 'woddi'@'127.0.0.1'; FLUSH PRIVILEGES;"
php /preview/seed.php
chown -R www-data:www-data /var/www/html/storage /var/www/html/uploads /var/www/html/config.php
exec apache2-foreground
