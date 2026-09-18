# Usa a imagem oficial do PHP com Apache
FROM php:8.2-apache

# Instala dependências do sistema e extensões PHP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        pdo_pgsql \
        pgsql

# Habilita o mod_rewrite do Apache
RUN a2enmod rewrite

# Define o diretório de trabalho
WORKDIR /var/www/html

# Copia os arquivos do projeto
COPY . .

# Configura permissões (ignora pastas que não existem)
RUN chown -R www-data:www-data /var/www/html \
    && ( [ -d /var/www/html/storage ] && chmod -R 755 /var/www/html/storage || true ) \
    && ( [ -d /var/www/html/bootstrap/cache ] && chmod -R 755 /var/www/html/bootstrap/cache || true )

# Configura o VirtualHost para o Apache
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Porta exposta
EXPOSE 80

# Comando para iniciar o Apache
CMD ["apache2-foreground"]
