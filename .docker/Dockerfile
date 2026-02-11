FROM kooldev/php:8.4

RUN set -eux; \
    if command -v apt-get >/dev/null 2>&1; then \
      apt-get update; \
      apt-get install -y --no-install-recommends qpdf openssl ca-certificates php-pear php8.4-dev make gcc; \
      rm -rf /var/lib/apt/lists/*; \
    elif command -v apk >/dev/null 2>&1; then \
      apk add --no-cache qpdf openssl ca-certificates php84-pear php84-dev make gcc musl-dev; \
    else \
      echo "Unsupported base image: package manager not found"; \
      exit 1; \
    fi

RUN set -eux; \
    if pecl list | grep -qi '^pcov '; then \
      echo "pcov already installed"; \
    else \
      pecl install pcov; \
    fi; \
    if ! php -m | grep -qi '^pcov$'; then \
      echo "extension=pcov.so" > /usr/local/etc/php/conf.d/pcov.ini; \
    else \
      : > /usr/local/etc/php/conf.d/pcov.ini; \
    fi; \
    echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/pcov.ini; \
    echo "pcov.directory=/app/src" >> /usr/local/etc/php/conf.d/pcov.ini

RUN sed -i '/^default = default_sect/a legacy = legacy_sect' /etc/ssl/openssl.cnf
RUN sed -i '/^\[default_sect\]/a activate = 1' /etc/ssl/openssl.cnf
RUN printf "[legacy_sect]\nactivate = 1" >> /etc/ssl/openssl.cnf

CMD [ "php-fpm" ]
