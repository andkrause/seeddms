# Stage 1: Download and extract SeedDMS
FROM andy008/php4seeddms:8.5.1-apache-trixie AS downloader

# Install curl for downloading
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*

# Copy the URL file and read it
COPY seedms-apache/SEEDDMS_URL /tmp/seeddms_url.txt

# Read URL from file and download SeedDMS
RUN SEEDDMS_URL=$(cat /tmp/seeddms_url.txt | tr -d '\n\r') \
    && mkdir -p /tmp/seeddms \
    && curl -fsSL "$SEEDDMS_URL" -o /tmp/seeddms.tar.gz \
    && tar -xzC /tmp/seeddms -f /tmp/seeddms.tar.gz \
    && rm /tmp/seeddms.tar.gz

# Stage 2: Final image
FROM andy008/php4seeddms:8.5.1-apache-trixie

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/seeddms/seeddms60x/www/

# Configure Apache to use the new document root
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Create SeedDMS directory
RUN mkdir -p /var/seeddms

# Copy SeedDMS files from downloader stage
COPY --from=downloader /tmp/seeddms/seeddms60x /var/seeddms/seeddms60x

#Only for Installation (Temporary Fix)
#BEGIN
#RUN ln -sf /var/seeddms/seeddms60x/vendor /var/seeddms/seeddms60x/seeddms-6.0.37/vendor

# Fix namespace issue: PDO needs to be fully qualified in namespace context
# The file is in Seeddms\Seeddms namespace, so "new PDO" becomes "new Seeddms\Seeddms\PDO"
# We need to use "\PDO" to reference the global PDO class
#RUN sed -i 's/new PDO(/new \\PDO(/g' /var/seeddms/seeddms60x/seeddms-6.0.37/inc/inc.ClassSettings.php
#END

# Set proper ownership and permissions
RUN chown -R www-data:www-data /var/seeddms/seeddms60x \
    && chmod -R 755 /var/seeddms/seeddms60x \
    && chmod -R 775 /var/seeddms/seeddms60x/data \
    && chmod -R 775 /var/seeddms/seeddms60x/conf

# Enable required Apache modules
RUN a2enmod rewrite headers

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Expose volumes for data and configuration persistence
# These match the volumes exposed by the usteinm/seeddms Docker image
VOLUME ["/var/seeddms/seeddms60x/data", "/var/seeddms/seeddms60x/conf"]

# Expose port 80
EXPOSE 80
