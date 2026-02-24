FROM php:8.2-apache AS base

# Gerekli PHP extension'larını kur
RUN apt-get update && apt-get install -y \
        libzip-dev \
        libonig-dev \
        libssl-dev \
        unzip \
        git \
    && docker-php-ext-install mbstring zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Composer'ı kopyala
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache vhost konfigürasyonu
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Çalışma dizini
WORKDIR /var/www/html

# Önce sadece composer dosyalarını kopyala (cache layer)
COPY composer.json ./

# Bağımlılıkları yükle (dev hariç)
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction

# Proje dosyalarını kopyala
COPY src/ src/
COPY tests/ tests/
COPY demo/ demo/

# Autoloader'ı yeniden oluştur
RUN composer dump-autoload --optimize --no-dev

# Dosya izinleri
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
