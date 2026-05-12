# Utilise l'image officielle PHP 8.3 avec Apache
FROM php:8.3-apache

# 1. Installation des dépendances système
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

# 2. Installation des extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql zip mbstring exif pcntl bcmath xml

# 3. SOLUTION DEFINITIVE AU CRASH MPM : Ecrasement de la config de chargement
# Cette commande force Apache à ne charger QUE le module prefork, peu importe les autres fichiers présents.
RUN printf "LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so\n" > /etc/apache2/mods-available/mpm_prefork.load \
    && a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite

# 4. Configuration du dossier racine /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Préparation de l'application
WORKDIR /var/www/html
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/html/
RUN composer install --no-dev --optimize-autoloader

# 6. Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
