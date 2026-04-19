FROM php:8.1-apache

# Update and install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libicu-dev \
    libonig-dev \
    libldap2-dev \
    libcurl4-openssl-dev \
    unzip \
    zip \
    curl \
    openssl \
    iproute2 \
    net-tools \
    iputils-ping \
    wget \
    && rm -rf /var/lib/apt/lists/*

# Configure GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Configure LDAP
RUN docker-php-ext-configure ldap --with-ldap

# Install PHP Extensions (matching typical XAMPP)
RUN docker-php-ext-install \
    gd \
    mysqli \
    pdo \
    pdo_mysql \
    zip \
    soap \
    intl \
    mbstring \
    bcmath \
    calendar \
    exif \
    gettext \
    ldap

# Enable Apache Modules
RUN a2enmod ssl rewrite headers expires

# Default HTTPS port — docker-compose override edə bilər
# Apache HTTP→HTTPS redirect-i üçün istifadə olunur
ENV SRCS_HTTPS_PORT=443

# Create SSL directory
RUN mkdir -p /etc/ssl/private

# OpenSSL config — düzgün server sertifikatı üçün
# basicConstraints=CA:FALSE (CA olmasın, yalnız server)
# subjectAltName = DNS + IP (müasir brauzerlər üçün)
RUN printf '[req]\ndistinguished_name = req_distinguished_name\nx509_extensions = v3_req\nprompt = no\n\n[req_distinguished_name]\nC = AZ\nST = Baku\nL = Baku\nO = S-RCS\nCN = localhost\n\n[v3_req]\nbasicConstraints = CA:FALSE\nkeyUsage = digitalSignature, keyEncipherment\nextendedKeyUsage = serverAuth\nsubjectAltName = @alt_names\n\n[alt_names]\nDNS.1 = localhost\nDNS.2 = *.localhost\nDNS.3 = srcs\nDNS.4 = *.srcs.local\nIP.1 = 127.0.0.1\nIP.2 = 0.0.0.0\n' > /etc/ssl/openssl-srcs.conf

# Generate Self-Signed SSL Certificate (düzgün SAN + CA:FALSE)
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/apache-selfsigned.key \
    -out /etc/ssl/certs/apache-selfsigned.crt \
    -config /etc/ssl/openssl-srcs.conf \
    -extensions v3_req

# Set permissions
RUN chmod 600 /etc/ssl/private/apache-selfsigned.key

# Create temp directories with proper permissions
RUN mkdir -p /var/www/html/temp/secure_store && \
    chown -R www-data:www-data /var/www/html/temp && \
    chmod -R 755 /var/www/html/temp

# LDAP SSL Configuration - disable certificate verification for development
RUN mkdir -p /etc/ldap && echo "TLS_REQCERT never" > /etc/ldap/ldap.conf

# Entrypoint script — mount edilmiş volume-larda permission-ları düzəldir
# (bind mount host permission-larını saxlayır, ona görə runtime-da chown lazımdır)
COPY entrypoint.sh /usr/local/bin/srcs-entrypoint.sh
RUN chmod +x /usr/local/bin/srcs-entrypoint.sh

# Start Apache via entrypoint
ENTRYPOINT ["/usr/local/bin/srcs-entrypoint.sh"]
CMD ["apache2-foreground"]
