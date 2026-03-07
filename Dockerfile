# Usamos una imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalamos la extensión mysqli que necesita tu proyecto
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copiamos todos los archivos de tu proyecto al contenedor
COPY . /var/www/html/

# Damos permisos a las carpetas donde se suben archivos y backups
RUN chmod -R 777 /var/www/html/uploads /var/www/html/backups

# Exponemos el puerto 80
EXPOSE 80