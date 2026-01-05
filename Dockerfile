# Stage 1: Download and extract SeedDMS
FROM andy008/php4seeddms:8.5.1-apache-trixie AS downloader

# Install curl and xz-utils for downloading
RUN apt-get update && \
    apt-get install -y --no-install-recommends curl ca-certificates xz-utils && \
    rm -rf /var/lib/apt/lists/*

# Download and extract s6-overlay
# Map Debian architecture names to s6-overlay naming convention
RUN DEB_ARCH=$(dpkg --print-architecture) && \
    case "$DEB_ARCH" in \
        amd64) S6_ARCH=x86_64 ;; \
        arm64) S6_ARCH=aarch64 ;; \
        armhf) S6_ARCH=armhf ;; \
        armel) S6_ARCH=armel ;; \
        i386) S6_ARCH=x86 ;; \
        *) S6_ARCH="$DEB_ARCH" ;; \
    esac && \
    curl -L https://github.com/just-containers/s6-overlay/releases/latest/download/s6-overlay-noarch.tar.xz -o /tmp/s6-overlay-noarch.tar.xz && \
    curl -L https://github.com/just-containers/s6-overlay/releases/latest/download/s6-overlay-${S6_ARCH}.tar.xz -o /tmp/s6-overlay-arch.tar.xz && \
    mkdir -p /tmp/s6-overlay && \
    tar -C /tmp/s6-overlay -Jxpf /tmp/s6-overlay-noarch.tar.xz && \
    tar -C /tmp/s6-overlay -Jxpf /tmp/s6-overlay-arch.tar.xz && \
    rm -f /tmp/s6-overlay-*.tar.xz

# Copy the URL file and read it
COPY ./SEEDDMS_URL /tmp/seeddms_url.txt

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

# Scheduler interval in seconds (default: 300 = 5 minutes)
ENV SEEDDMS_SCHEDULER_INTERVAL=300

# s6-overlay configuration: give services 20 seconds to stop gracefully before SIGKILL
ENV S6_KILL_GRACETIME=20000

# Configure Apache to use the new document root (expand at build time)
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Create SeedDMS directory
RUN mkdir -p /var/seeddms

# Copy SeedDMS files from downloader stage
COPY --from=downloader /tmp/seeddms/seeddms60x /var/seeddms/seeddms60x

# Copy LLM Classifier extension
COPY ext/llmclassifier /var/seeddms/seeddms60x/www/ext/llmclassifier

#BEGIN of temporary fix that prevents installing seeddms 6.0.37 
# see https://sourceforge.net/p/seeddms/tickets/573/#6022
RUN ln -sf /var/seeddms/seeddms60x/vendor /var/seeddms/seeddms60x/seeddms-6.0.37/vendor

# Fix namespace issue: PDO needs to be fully qualified in namespace context
# The file is in Seeddms\Seeddms namespace, so "new PDO" becomes "new Seeddms\Seeddms\PDO"
# We need to use "\PDO" to reference the global PDO class
RUN sed -i 's/new PDO(/new \\PDO(/g' /var/seeddms/seeddms60x/seeddms-6.0.37/inc/inc.ClassSettings.php
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

# Copy s6-overlay from downloader stage
COPY --from=downloader /tmp/s6-overlay/ /

# Copy s6-rc service definitions
COPY services/s6-rc.d/ /etc/s6-overlay/s6-rc.d/
RUN chmod +x /etc/s6-overlay/s6-rc.d/apache/run /etc/s6-overlay/s6-rc.d/apache/finish \
/etc/s6-overlay/s6-rc.d/seeddms-scheduler/run /etc/s6-overlay/s6-rc.d/seeddms-scheduler/finish /etc/s6-overlay/s6-rc.d/seeddms-scheduler/scheduler-loop

# Expose volumes for data and configuration persistence
# These match the volumes exposed by the usteinm/seeddms Docker image
VOLUME ["/var/seeddms/seeddms60x/data", "/var/seeddms/seeddms60x/conf"]

# Expose port 80
EXPOSE 80

# Ensure Docker sends SIGTERM to PID 1 on stop (base images often set SIGWINCH)
STOPSIGNAL SIGTERM

# Use s6-overlay as entrypoint
ENTRYPOINT ["/init"]