FROM php:8.2-fpm

ARG user
ARG uid

# To work mongoDB install libcurl4-openssl-dev pkg-config libssl-dev
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources && \
    apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    ca-certificates \
    gnupg \
    libzip-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    libzip-dev && \
    pecl install mongodb && docker-php-ext-enable mongodb


# Prepare repos
RUN mkdir -p /etc/apt/keyrings
RUN curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
RUN echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list

RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /etc/apt/keyrings/microsoft.gpg
RUN echo "deb [arch=amd64, signed-by=/etc/apt/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" | tee /etc/apt/sources.list.d/mssql-release.list


# Install NodeJS
RUN apt-get update && apt-get install -y nodejs
# RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Update and install necessary packages for mssql driver
RUN apt-get install -y --no-install-recommends unixodbc-dev unixodbc \
    && sed -i 's,^\(MinProtocol[ ]*=\).*,\1'TLSv1.0',g' /etc/ssl/openssl.cnf \
    && sed -i 's,^\(CipherString[ ]*=\).*,\1'DEFAULT@SECLEVEL=1',g' /etc/ssl/openssl.cnf

# Install MS ODBC Driver for SQL Server - Debian 12
RUN ACCEPT_EULA=Y apt-get -y --no-install-recommends install msodbcsql18 \
    && pecl install sqlsrv-5.12.0 \
    && pecl install pdo_sqlsrv-5.12.0 \
    && echo "extension=pdo_sqlsrv.so" >> `php --ini | grep "Scan for additional .ini files" | sed -e "s|.*:\s*||"`/30-pdo_sqlsrv.ini \
    && echo "extension=sqlsrv.so" >> `php --ini | grep "Scan for additional .ini files" | sed -e "s|.*:\s*||"`/30-sqlsrv.ini \
    && apt clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*


# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml zip
RUN docker-php-ext-enable zip

# Get latest Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

RUN chmod +x /home
# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www/html

# COPY [".", "/var/www"]

USER $user