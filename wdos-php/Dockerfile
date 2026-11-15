# WDOS test deployment on Render. The Apache rules are written here as well as in
# .htaccess because uploading through the GitHub website from Windows skips dotfiles.
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers deflate
# report and task documents up to 10 MB arrive as base64 inside JSON, so the request limit is set above that
RUN printf 'upload_max_filesize=20M\npost_max_size=24M\nmemory_limit=256M\nmax_execution_time=120\n' > /usr/local/etc/php/conf.d/wdos.ini
RUN printf '%s\n' \
 '<Directory /var/www/html>' \
 '  Options -Indexes' \
 '  AllowOverride None' \
 '  Require all granted' \
 '  RewriteEngine On' \
 '  RewriteRule ^(lib|migrations|docs)(/|$) - [F,L]' \
 '  RewriteRule ^(config\.local\.php|config\.sample\.php|Dockerfile|README-DEPLOY\.md|\.user\.ini)$ - [F,L]' \
 '  RewriteRule ^api(/.*)?$ api/index.php [QSA,L]' \
 '  <FilesMatch "^(sw\.js|index\.html)$">' \
 '    Header set Cache-Control "no-cache"' \
 '  </FilesMatch>' \
 '  Header always set X-Content-Type-Options "nosniff"' \
 '</Directory>' \
 'ServerName wdos' \
 > /etc/apache2/conf-available/wdos.conf && a2enconf wdos
COPY . /var/www/html/
# Render gives the port in $PORT
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-10000}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
