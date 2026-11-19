# One image for every PHP role: website (FrankenPHP), Reverb, MQTT listener, artisan commands.
# FrankenPHP = a PHP-aware web server in a single binary (no separate nginx + php-fpm).

# ---------- base: what every environment needs ----------
FROM dunglas/frankenphp:1-php8.4 AS base

# PHP extensions Laravel needs on top of the base image:
#   pdo_pgsql = talk to PostgreSQL, pcntl = signal handling for long-running commands (Reverb, queues)
RUN install-php-extensions pdo_pgsql pcntl intl zip opcache

# Composer (PHP's package manager), copied from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ---------- dev: base + Node.js for Vite ----------
# Vite needs PHP too: the Wayfinder plugin runs `php artisan wayfinder:generate`
# to create TypeScript helpers for Laravel routes. So Vite runs in this image, not plain node.
FROM base AS dev
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
 && node --version && npm --version

# In development the code is mounted from the Mac (see docker-compose.yml), so nothing is copied here.
# A production stage (code copied in, composer install, npm run build) will be added for the Pi.
