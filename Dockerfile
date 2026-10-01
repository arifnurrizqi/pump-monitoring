FROM node:22-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-fpm-alpine AS app
RUN apk add --no-cache icu-libs libzip oniguruma su-exec libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev postgresql-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install -j$(nproc) pdo_pgsql mbstring intl zip bcmath pcntl opcache \
    && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=frontend /build/public/build ./public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-hydroflow.ini
COPY docker/php/entrypoint.sh /usr/local/bin/hydroflow-entrypoint
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/hydroflow-entrypoint
ENTRYPOINT ["hydroflow-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.27-alpine AS nginx
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD wget -q -O /dev/null http://127.0.0.1/up || exit 1
