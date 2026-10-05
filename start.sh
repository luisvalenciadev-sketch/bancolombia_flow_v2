#!/bin/bash
# Script de inicio para Render.com
# Render inyecta la variable $PORT automaticamente

PORT=${PORT:-10000}

echo "=============================================="
echo "  Bancolombia Flow v2 - Iniciando servidor"
echo "  Puerto: $PORT"
echo "=============================================="

# Reemplazar puerto en configuracion de Apache
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf

# Asegurar que data/ tenga permisos correctos
chmod 777 /var/www/html/data

# Iniciar Apache en foreground
exec apache2-foreground
