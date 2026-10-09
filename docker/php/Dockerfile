# SpeakVerse M00: image is built from the EXISTING composer.lock/package-lock.json.
FROM public.ecr.aws/docker/library/node:22-bookworm-slim AS assets
WORKDIR /src
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM public.ecr.aws/docker/library/php:8.4-fpm-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl gd bcmath opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=public.ecr.aws/docker/library/composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader
COPY . .
COPY --from=assets /src/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

FROM public.ecr.aws/docker/library/nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=assets /src/public/build /var/www/html/public/build
