 FROM php:8.2-apache

# Install the MySQL extensions required for PDO database connections
RUN docker-php-ext-install pdo pdo_mysql

# Copy all your project files into the web server's default directory
COPY . /var/www/html/

# Enable Apache rewrite rules (useful for custom clean routing)
RUN a2enmod rewrite

# Expose port 80 for web traffic
EXPOSE 80
