# ============================================
# Stage 1: Builder - Installation des dépendances Composer uniquement
# ============================================
FROM composer:latest AS composer

# Stage pour installer les dépendances PHP avec Composer
# On utilise juste l'image Composer, pas besoin de PHP complet
FROM php:8.4-cli-alpine AS builder

# Installer uniquement les outils nécessaires pour Composer
RUN apk add --no-cache \
    git \
    unzip

# Installer Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /app

# Copier uniquement les fichiers nécessaires pour composer
COPY composer.json composer.lock ./

# Installer les dépendances PHP (sans dev)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs

# ============================================
# Stage 2: Runtime - Image finale minimale
# ============================================
FROM php:8.4-cli-alpine AS runtime

# Installer uniquement les dépendances runtime nécessaires
RUN apk add --no-cache \
    # Bibliothèques runtime
    libpng \
    libjpeg-turbo \
    freetype \
    oniguruma \
    libxml2 \
    postgresql-libs \
    libzip \
    # Dépendances de build (seront supprimées après)
    && apk add --no-cache --virtual .build-deps \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    postgresql-dev \
    libzip-dev \
    $PHPIZE_DEPS \
    # Compiler les extensions PHP
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    # Installer Redis
    && pecl install redis \
    && docker-php-ext-enable redis \
    # Nettoyer les dépendances de build
    && apk del .build-deps \
    # Nettoyer les caches PECL
    && rm -rf /tmp/pear \
    # Nettoyer les caches APK
    && rm -rf /var/cache/apk/*

# Créer un utilisateur non-root pour la sécurité
RUN addgroup -g 1000 jeffrey && \
    adduser -D -u 1000 -G jeffrey jeffrey

# Définir le répertoire de travail
WORKDIR /var/www

# Copier les dépendances installées depuis le stage builder
COPY --from=builder --chown=jeffrey:jeffrey /app/vendor ./vendor

# Copier les fichiers de l'application (seulement ce qui est nécessaire)
COPY --chown=jeffrey:jeffrey . .

# Créer les répertoires de stockage et cache avec les bonnes permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    storage/logs \
    bootstrap/cache \
    && chown -R jeffrey:jeffrey storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Optimiser Laravel pour la production (avant de passer à l'utilisateur non-root)
USER root
RUN php artisan config:cache || true \
    && php artisan route:cache || true \
    && php artisan view:cache || true \
    # Nettoyer les fichiers temporaires Laravel
    && find . -type f -name "*.php~" -delete \
    && find . -type d -name ".git" -prune -exec rm -rf {} + || true

# Passer à l'utilisateur non-root
USER jeffrey

# Exposer le port
EXPOSE 8000

# Commande de démarrage
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
