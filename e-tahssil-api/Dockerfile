# Utilise une image officielle PHP optimisée avec le serveur Apache
FROM php:8.3-apache
# Installe les librairies système requises pour Laravel et GD
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql zip

# # Active le module de réécriture d'Apache (indispensable pour les routes Laravel)
# RUN a2enmod rewrite

# # Modifie la configuration d'Apache pour pointer directement vers le dossier "public"
# ENV APACHE_DOCUMENT_ROOT /var/www/html/public
# RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
# RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
# Désactiver les MPM conflictuels et s'assurer que prefork est actif
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork

# Active le module de réécriture d'Apache (indispensable pour les routes Laravel)
RUN a2enmod rewrite
# Définit le dossier de travail
WORKDIR /var/www/html

# Récupère Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copie tout le code de ton API dans le conteneur
COPY . /var/www/html/

# Installe les dépendances PHP (y compris phpspreadsheet et phpword)
RUN composer install --no-dev --optimize-autoloader

# Donne les droits d'écriture à Laravel pour les logs et le cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose le port 80 pour Railway
EXPOSE 80
