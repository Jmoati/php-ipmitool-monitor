FROM php:cli

RUN apt update && \
    apt install -y ipmitool wget

RUN wget -O /usr/share/misc/enterprise-numbers.txt https://jff.email/cgit/ipmitool.git/plain/debian/enterprise-numbers.txt?h=debian/1.8.19-5

COPY server-fancontrol.php /app/server-fancontrol.php

CMD ["php", "/app/server-fancontrol.php"]