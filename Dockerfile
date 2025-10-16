# Nexa Worker - Dockerfile
FROM php:8.3-apache

# Installer extensions PHP
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl

# Configurer Apache
RUN a2enmod rewrite
COPY ./public /var/www/html

# Port
EXPOSE 80 8080

# Variables d'environnement
ENV ENGINE_URL=https://nexafoundry.ai/love/public
ENV ENGINE_TOKEN=nexa-engine-secret
ENV WORKER_ID=worker-docker

# Démarrer l'orchestrateur en arrière-plan + Apache
CMD php /var/www/html/../worker/orchestrator.php & apache2-foreground

