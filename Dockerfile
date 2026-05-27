# ─── Starlight Express – container image ─────────────────────────────────────
# PHP 8.2 + Apache.  Enables mod_rewrite and mod_headers to honour .htaccess.
# PHP mail() is routed through msmtp, configured at container start by
# docker/entrypoint.sh from SMTP_* environment variables.
#
# Dev:  SMTP_HOST=mailhog, SMTP_PORT=1025  (Mailhog catches all mail)
# Prod: Set SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASS in your .env
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.2-apache

# Enable Apache modules required by .htaccess
RUN a2enmod rewrite headers

# Allow .htaccess to override everything (needed for RewriteEngine)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install cURL extension (reCAPTCHA verification) + msmtp (mail routing)
RUN apt-get update -y && \
    apt-get install -y libcurl4-openssl-dev msmtp && \
    docker-php-ext-install curl && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Point PHP mail() → msmtp
RUN echo 'sendmail_path = /usr/bin/msmtp -t --read-envelope-from' \
    >> /usr/local/etc/php/php.ini

# Copy the startup script first (before the bulk COPY so it lands at /
# not inside the web root)
COPY docker/entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Copy site into the web root
COPY . /var/www/html/

# Remove server-only files from the web root so they aren't web-accessible
RUN rm -rf  /var/www/html/docker \
            /var/www/html/docker-compose.yml \
            /var/www/html/docker-compose.*.yml \
            /var/www/html/Dockerfile \
            /var/www/html/.dockerignore \
            /var/www/html/.env* \
            /var/www/html/.git \
            /var/www/html/.gitignore && \
    # Scrub any Windows Zone.Identifier stubs that slipped past .dockerignore
    find /var/www/html -name '*:Zone.Identifier' -delete

# Tighten permissions
RUN chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

ENTRYPOINT ["/docker-entrypoint.sh"]
