FROM php:8.2-apache

# Copy all files to Apache's web root
COPY . /var/www/html/

# Apache needs to listen on the port Render assigns
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE ${PORT}

CMD ["apache2-foreground"]
