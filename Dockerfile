# Use a imagem oficial do PHP 8.2 com Apache
    FROM php:8.2-apache

    # Defina o diretório de trabalho
    WORKDIR /var/www/html

    # Instale dependências do sistema e extensões PHP
    # Atualize o gerenciador de pacotes e instale dependências comuns
    RUN apt-get update && apt-get install -y \
        git \
        curl \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        zip \
        unzip \
        libpq-dev \
    # Instale a extensão Redis via PECL
        && pecl install redis \
        && docker-php-ext-enable redis \
    # Configure e instale as extensões PHP
        && docker-php-ext-configure gd --with-freetype --with-jpeg \
        && docker-php-ext-install -j$(nproc) gd \
        && docker-php-ext-install pdo pdo_mysql pdo_pgsql bcmath exif intl opcache zip

    # Instale o Composer globalmente
    COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

    # Copie os arquivos da aplicação (primeiro composer.json e composer.lock para aproveitar o cache do Docker)
    COPY composer.json composer.lock ./

    # Instale as dependências do Composer (sem dependências de desenvolvimento, otimizar autoloader)
    RUN composer install --no-interaction --no-plugins --no-scripts --no-dev --prefer-dist --optimize-autoloader

    # Copie o restante dos arquivos da aplicação
    COPY . .

    # Defina as permissões corretas para as pastas do Laravel
    RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
        && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

    # Ative o mod_rewrite do Apache para URLs amigáveis do Laravel
    RUN a2enmod rewrite

    # Exponha a porta 80 (padrão do Apache)
    EXPOSE 80

    # Comando para iniciar o Apache em primeiro plano
    # A Render pode sobrescrever isso com o startCommand da configuração do serviço,
    # mas é bom ter um CMD padrão. O script de entrada do Laravel espera que
    # o Apache sirva a partir do diretório /public.
    # Esta configuração do Apache já deve estar configurada para apontar para /var/www/html/public
    # nas imagens php:apache. Se não, precisaria de um virtual host customizado.
    # Por enquanto, vamos assumir que a imagem base php:apache lida com isso ou ajustaremos depois.

    # O script entrypoint padrão do php:apache já deve iniciar o Apache.
    # Se precisarmos de um entrypoint customizado (por exemplo, para rodar migrações),
    # podemos adicionar um script entrypoint.sh. Por ora, manteremos simples.

    # Limpe o cache do apt
    RUN apt-get clean && rm -rf /var/lib/apt/lists/* 