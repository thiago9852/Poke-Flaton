# Use a imagem oficial do PHP com Apache
FROM php:8.4-apache

# Instalar dependências do sistema e extensões do PHP necessárias para o Symfony
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        zip \
        intl \
        opcache \
        gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite do Apache para o roteamento do Symfony
RUN a2enmod rewrite

# Configurar o Apache para apontar para a pasta public do Symfony
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Instalar o Composer mais recente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Definir diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos do projeto
COPY . .

# Variáveis de ambiente padrão para produção
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV COMPOSER_ALLOW_SUPERUSER=1

# Instalar dependências do Composer para produção (sem dev dependencies)
RUN composer install --no-interaction --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs --prefer-dist

# Instalar assets JS externos e compilar assets para produção
RUN DATABASE_URL=sqlite:///:memory: php bin/console importmap:install
RUN DATABASE_URL=sqlite:///:memory: php bin/console asset-mapper:compile

# Criar pastas necessárias e dar permissões corretas para o Apache
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var public \
    && chmod -R 775 var public

# Expor a porta 80
EXPOSE 80

# Iniciar as migrações, corrigir permissões do cache/logs e depois iniciar o Apache
CMD ["sh", "-c", "php bin/console doctrine:migrations:migrate --no-interaction && chown -R www-data:www-data var && apache2-foreground"]
