FROM php:8.3-apache

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Instalar dependências de sistema, FFmpeg e Python 3
RUN apt-get update && apt-get install -y \
    ffmpeg \
    python3 \
    python3-pip \
    python3-venv \
    git \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip

# Criar virtual environment do Python e instalar faster-whisper
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Instalar faster-whisper dentro do venv
RUN pip install --no-cache-dir faster-whisper

# Configurar o Apache para apontar para a pasta public do projeto
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configurar limites de upload e tempo de execução no PHP para arquivos de vídeo
RUN echo "upload_max_filesize=500M\npost_max_size=500M\nmemory_limit=512M\nmax_execution_time=600\nmax_input_time=600" > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Copiar os arquivos do projeto para o container
COPY . /var/www/html

# Garantir permissões corretas para o servidor web escrever na pasta de storage
RUN chown -R www-data:www-data /var/www/html
