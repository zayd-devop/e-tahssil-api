# Utilise l'image officielle PHP 8.3 avec Apache (Debian Bookworm)
FROM php:8.3-apache

# 1. Installation des dépendances système nécessaires
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

# 2. Configuration et installation des extensions PHP (y compris GD pour Excel/Word)
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

# 3. SOLUTION AU CRASH APACHE : Suppression physique des MPM conflictuels
# On force Apache à n'utiliser que 'prefork' pour PHP
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite

# 4. Configuration du dossier racine d'Apache pour pointer vers /public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Préparation de l'application
WORKDIR /var/www/html

# Récupération de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copie de tout le code du projet
COPY . /var/www/html/

# Installation des dépendances PHP (via composer.lock pour garantir la version 8.3)
RUN composer install --no-dev --optimize-autoloader

# 6. Gestion des permissions (Crucial pour Laravel)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Exposition du port par défaut
EXPOSE 80

# Commande de lancement
CMD ["apache2-foreground"]
