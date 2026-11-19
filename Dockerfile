# Temporary preview of the WODDI Institute PHP build. Apache, PHP and a throwaway MariaDB in one container.
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends mariadb-server && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql && a2enmod rewrite headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf
COPY app/ /var/www/html/
COPY preview/ /preview/
RUN chmod +x /preview/start.sh && chown -R www-data:www-data /var/www/html
CMD ["/preview/start.sh"]
