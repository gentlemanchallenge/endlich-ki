FROM php:8-apache
RUN a2enmod rewrite headers expires && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
RUN apt-get update && apt-get install -y unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY composer.json /var/www/html/
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader
COPY . /var/www/html/
RUN rm -f /var/www/html/Dockerfile /var/www/html/nginx.conf /var/www/html/README.md
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data
EXPOSE 80
