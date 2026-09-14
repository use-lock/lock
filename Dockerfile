# syntax=docker/dockerfile:1

FROM serversideup/php:8.5-frankenphp AS base

USER root
RUN install-php-extensions bcmath gmp intl

FROM base AS vendor

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/composer/cache \
    composer install --no-dev --no-scripts --no-autoloader --no-progress --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --no-interaction

FROM node:24-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN --mount=type=cache,target=/root/.npm \
    npm install --no-audit --no-fund

# The Lattice Vite plugin discovers component packages from vendor/composer/installed.json.
COPY --from=vendor /var/www/html/vendor ./vendor
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN VITE_APP_NAME=Lock npm run build

FROM base AS app

ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_APP_DEPLOY=false \
    HEALTHCHECK_PATH=/up \
    DB_CONNECTION=pgsql \
    OCTANE_SERVER=frankenphp \
    PHP_OPCACHE_ENABLE=1 \
    APP_NAME=Lock \
    LOG_CHANNEL=stack \
    LOG_STACK=stderr,sentry_logs \
    LOG_LEVEL=info \
    SESSION_SECURE_COOKIE=true

COPY --chmod=755 <<'EOF' /etc/entrypoint.d/60-app-deploy.sh
#!/bin/sh
if [ "$AUTORUN_APP_DEPLOY" = "true" ]; then
    echo "🚀 Running the release step: \"php artisan app:deploy\"..."
    php "$APP_BASE_DIR/artisan" app:deploy --no-interaction
fi
EOF

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

USER www-data

CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--port=8080"]
