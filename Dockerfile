FROM composer AS composer
FROM php:cli AS php

RUN apt update && \
    apt install -y ipmitool wget unzip

RUN wget -O /usr/share/misc/enterprise-numbers.txt https://jff.email/cgit/ipmitool.git/plain/debian/enterprise-numbers.txt?h=debian/1.8.19-5

FROM php AS build

RUN wget https://clue.engineering/phar-composer-latest.phar && \
    mv phar-composer-latest.phar /usr/local/bin/phar-composer && \
    chmod +x /usr/local/bin/phar-composer

RUN echo "phar.readonly = Off" >> /usr/local/etc/php/php.ini

COPY . /build/
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /build

RUN composer install && \
    phar-composer build /build/composer.json /build/fancontrol && \
    chmod +x /build/fancontrol

FROM php AS final

WORKDIR /app
COPY --from=build /build/fancontrol /app/fancontrol
CMD ["php", "/app/fancontrol"]