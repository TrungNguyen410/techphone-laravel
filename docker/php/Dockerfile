FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql intl zip opcache gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf 'upload_max_filesize=8M\npost_max_size=40M\nmemory_limit=256M\ndate.timezone=Asia/Ho_Chi_Minh\nrealpath_cache_ttl=600\nopcache.revalidate_freq=1\n' > /usr/local/etc/php/conf.d/techphone.ini \
    && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf

COPY entrypoint.sh /usr/local/bin/techphone-entrypoint
# Bỏ ký tự CRLF nếu file được sửa trên Windows.
RUN sed -i 's/\r$//' /usr/local/bin/techphone-entrypoint && chmod +x /usr/local/bin/techphone-entrypoint

WORKDIR /var/www/html
CMD ["techphone-entrypoint"]
