FROM php:8.2-apache

# Habilitar mod_rewrite para .htaccess
RUN a2enmod rewrite

# Crear directorio de trabajo
WORKDIR /var/www/html

# Copiar todo el proyecto
COPY . /var/www/html/

# Crear directorio data con permisos de escritura para PHP
RUN mkdir -p /var/www/html/data && chmod 777 /var/www/html/data

# Script de inicio que configura el puerto dinamico de Render
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Render inyecta $PORT en runtime. Usamos el script para configurar Apache.
CMD ["/start.sh"]
