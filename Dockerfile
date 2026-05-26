# ─── Starlight Express – local dev image ────────────────────────────────────
# PHP 8.2 + Apache.  Enables mod_rewrite and mod_headers to honour .htaccess.
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.2-apache

# Enable Apache modules required by .htaccess
RUN a2enmod rewrite headers

# Allow .htaccess to override everything (needed for RewriteEngine)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install cURL extension (used by track-proxy.php)
RUN apt-get update -y && \
    apt-get install -y libcurl4-openssl-dev && \
    docker-php-ext-install curl && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy site into the web root
COPY . /var/www/html/

# Tighten permissions
RUN chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80
