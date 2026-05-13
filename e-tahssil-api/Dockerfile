# 1. Image de base officielle PHP 8.3 avec Apache
FROM php:8.3-apache

# 2. Installation des dépendances système (Librairies pour GD, Zip, Git, etc.)
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    libonig-dev \
    libxml2-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. Installation des extensions PHP indispensables pour Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_mysql \
    zip \
    mbstring \
    exif \
    pcntl \
    bcmath \
    xml

# 4. Configuration du dossier racine pour pointer vers le dossier "public" de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Préparation du dossier de travail
WORKDIR /var/www/html

# 6. Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Copie de tout ton code source dans le conteneur
COPY . /var/www/html/

# 8. Installation des packages PHP (optimisé pour la production)
RUN composer install --no-dev --optimize-autoloader

# 9. Configuration des permissions cruciales pour Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 10. LA COMMANDE DE LANCEMENT MAGIQUE (Exécutée au démarrage du serveur)
# - Configure le port dynamique de Railway ($PORT)
# - Désactive les MPM conflictuels et active les bons
# - Exécute tes migrations de base de données
# - Lance Apache
CMD /bin/sh -c 'sed -i "s/80/${PORT:-80}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf; a2dismod mpm_event mpm_worker || true; a2enmod mpm_prefork rewrite; php artisan migrate --force; apache2-foreground'
