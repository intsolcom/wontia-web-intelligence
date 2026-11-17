FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx curl libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd

COPY directory/nginx.conf /etc/nginx/http.d/default.conf
COPY . /app

RUN mkdir -p /app/cache /app/vendor /app/public/assets/uploads \
    && cp /app/install/autoload.php /app/vendor/autoload.php \
    && chown -R www-data:www-data /app/cache /app/public/assets/uploads \
    && chmod 777 /app/cache /app/public/assets/uploads \
    && chmod -R a+rX /app/src /app/templates /app/directory \
    && printf "display_errors=Off\nlog_errors=On\nerror_log=/dev/stderr\nerror_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT\nupload_max_filesize=4M\npost_max_size=8M\nmemory_limit=256M\nmax_execution_time=60\n" > /usr/local/etc/php/php.ini

EXPOSE 80

CMD sh -c 'php-fpm -D && nginx -g "daemon off;"'
