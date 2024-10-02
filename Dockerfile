FROM php:cli

RUN apt update && \
    apt install -y ipmitool wget unzip

RUN wget -O /usr/share/misc/enterprise-numbers.txt https://jff.email/cgit/ipmitool.git/plain/debian/enterprise-numbers.txt?h=debian/1.8.19-5

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY server-fancontrol.php composer.json composer.lock ./

RUN composer install
CMD ["php", "/app/server-fancontrol.php"]