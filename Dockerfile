# ===================================================================
# 1. ADIM: TEMEL ORTAMI SEÇME
# ===================================================================
FROM php:8.2-apache
# 1.5. ADIM: COMPOSER'I KUR
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# ===================================================================
# 2. ADIM: SİSTEM BAĞIMLILIKLARINI KURMA (ÖNEMLİ DÜZELTME)
# ===================================================================
# PHP eklentilerini kurmadan önce, onların ihtiyaç duyduğu sistem
# kütüphanelerini kurmalıyız.
#
# apt-get update: Paket listesini günceller.
# apt-get install -y: Paketleri kurar. '-y' bayrağı, "evet" sorusuna
#   otomatik olarak onay verir.
# libsqlite3-dev: pdo_sqlite eklentisinin derlenebilmesi için gereken
#   temel SQLite geliştirme kütüphanesidir.
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    && rm -rf /var/lib/apt/lists/*

# ===================================================================
# 3. ADIM: GEREKLİ PHP EKLENTİLERİNİ KURMA
# ===================================================================
# Artık sistemde gerekli kütüphane olduğuna göre, PHP eklentilerini
# sorunsuz bir şekilde kurabiliriz.
RUN docker-php-ext-install pdo pdo_sqlite gd
# ===================================================================
# 4. ADIM: APACHE AYARLARINI YAPILANDIRMA
# ===================================================================
RUN a2enmod rewrite
