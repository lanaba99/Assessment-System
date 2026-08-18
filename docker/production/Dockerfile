# Production-oriented baseline image — NOT the dev image compose.yaml/Sail
# uses (that one bind-mounts the whole source tree and is meant to be
# rebuilt/reloaded constantly). This one COPIES a specific commit's code in
# at build time and produces an immutable image, matching how a real
# deployment should run: build once, deploy the same artifact everywhere.
#
# Still provider-agnostic on purpose — this is a starting baseline, not a
# finished deployment. What's genuinely provider-specific (real resource
# limits, secrets injection, TLS termination, autoscaling, log shipping) is
# documented in docs/PRODUCTION_OPERATIONS.md and docs/DEPLOYMENT_CHECKLIST.md
# instead of guessed at here.

FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --ignore-platform-reqs

FROM php:8.4-fpm-alpine AS app

# Matches the extensions this app's composer.lock actually declares
# (pdo_mysql/redis/bcmath aren't in that list but are required at runtime for
# the mysql tenant-database driver, Redis cache/queue, and Laravel's default
# money/decimal helpers respectively).
RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        libpng-dev \
        oniguruma-dev \
        curl-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        intl \
        zip \
        gd \
        mbstring \
        bcmath \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Production PHP/opcache tuning — safe, standard defaults, no secrets.
COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-production.ini

# Non-root: Alpine's php-fpm image ships a "www-data" user already; run as it
# rather than root (unlike compose.yaml's dev image, this one never needs to
# write into a bind-mounted host directory owned by a local dev user).
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

RUN php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
