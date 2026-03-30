FROM php:8.3-fpm

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libzip-dev \
        libonig-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        xml \
        curl \
        gd \
        zip \
        fileinfo \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP config overrides
COPY docker/php.ini /usr/local/etc/php/conf.d/99-flotacontrol.ini

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Copy application code
COPY . /var/www/html/

# Upload directories
RUN mkdir -p /var/www/html/uploads \
    && for subdir in vehiculos incidentes mantenimientos combustible operadores oc_cotizacion oc_factura vehiculo_documentos importaciones; do \
        mkdir -p /var/www/html/uploads/$subdir; \
    done \
    && chown -R www-data:www-data /var/www/html/uploads

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
