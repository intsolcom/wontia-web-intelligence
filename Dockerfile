FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx curl libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY . /app

RUN mkdir -p /app/public/assets/uploads /app/cache /app/vendor \
    && cp /app/install/autoload.php /app/vendor/autoload.php \
    && chown -R www-data:www-data /app/public/assets/uploads /app/cache \
    && chmod 777 /app/public/assets/uploads /app/cache \
    && chmod -R a+rX /app/src /app/templates \
    && printf "display_errors=Off\nlog_errors=On\nerror_log=/dev/stderr\nerror_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT\nupload_max_filesize=12M\npost_max_size=14M\nmemory_limit=256M\nmax_file_uploads=50\nmax_execution_time=120\n" > /usr/local/etc/php/php.ini

EXPOSE 80

CMD sh -c 'php-fpm -D && nginx -g "daemon off;"'
