FROM php:7.4-apache AS base

# Debian 11 (bullseye) LTS ended in August 2026 and its packages moved to archive.debian.org;
# the deb.debian.org indexes still list files that now 404. Use the archive and take the
# final bullseye security updates (the php:7.4 base image was last rebuilt in 2022).
RUN printf '%s\n' \
        'deb http://archive.debian.org/debian bullseye main' \
        'deb http://archive.debian.org/debian bullseye-updates main' \
        'deb http://archive.debian.org/debian-security bullseye-security main' \
        > /etc/apt/sources.list \
    && rm -f /etc/apt/sources.list.d/*.list \
    && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99archive \
    && apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y \
    && rm -rf /var/lib/apt/lists/*

FROM base AS php-build

# PHP 7.4 is end-of-life and the official php:7.4 images will not be rebuilt for
# PHP2Shell (CVE-2026-109618, CVE-2026-109619 - unauthenticated RCE, worst on mod_php).
# Rebuild PHP 7.4.33 from the source shipped in the base image plus the PHP security
# team's minimal backport (php/php-src@712e60f9, branch PHP-7.4-CVE-2026-109618-109619),
# with the same configure options as the official image.
COPY docker/php/php-7.4-CVE-2026-109618-109619.diff /usr/src/

RUN set -eux; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS patch apache2-dev \
        libargon2-dev libcurl4-openssl-dev libonig-dev libreadline-dev libsodium-dev \
        libsqlite3-dev libssl-dev libxml2-dev zlib1g-dev \
        libicu-dev libgd-dev; \
    mkdir /usr/src/php-patched; \
    tar -xJf /usr/src/php.tar.xz -C /usr/src/php-patched --strip-components=1; \
    patch -d /usr/src/php-patched -p1 < /usr/src/php-7.4-CVE-2026-109618-109619.diff; \
    tar -cJf /usr/src/php.tar.xz -C /usr/src php-patched; \
    rm -rf /usr/src/php-patched /usr/src/php.tar.xz.asc; \
    docker-php-source extract; \
    cd /usr/src/php; \
    export CFLAGS="$PHP_CFLAGS" CPPFLAGS="$PHP_CPPFLAGS" LDFLAGS="$PHP_LDFLAGS"; \
    gnuArch="$(dpkg-architecture --query DEB_BUILD_GNU_TYPE)"; \
    debMultiarch="$(dpkg-architecture --query DEB_BUILD_MULTIARCH)"; \
    ./configure \
        --build="$gnuArch" \
        --with-config-file-path="$PHP_INI_DIR" \
        --with-config-file-scan-dir="$PHP_INI_DIR/conf.d" \
        --enable-option-checking=fatal \
        --with-mhash --with-pic --enable-ftp --enable-mbstring --enable-mysqlnd \
        --with-password-argon2 --with-sodium=shared \
        --with-pdo-sqlite=/usr --with-sqlite3=/usr \
        --with-curl --with-iconv --with-openssl --with-readline --with-zlib \
        --disable-phpdbg \
        --with-libdir="lib/$debMultiarch" \
        --disable-cgi \
        --with-apxs2; \
    make -j"$(nproc)"; \
    find -type f -name '*.a' -delete; \
    make install; \
    find /usr/local -type f -perm '/0111' -exec sh -euxc 'strip --strip-all "$@" || :' -- '{}' +; \
    make clean; \
    cd /; \
    docker-php-ext-install mysqli bcmath intl gd; \
    docker-php-source delete; \
    php -r 'exit(PHP_VERSION === "7.4.33" ? 0 : 1);'

FROM base

RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    libicu-dev \
    libgd-dev \
    openssl \
    && rm -rf /var/lib/apt/lists/*

# Patched PHP (CLI, extensions, ini scan dir) and the patched mod_php for Apache.
COPY --from=php-build /usr/local/ /usr/local/
COPY --from=php-build /usr/lib/apache2/modules/libphp7.so /usr/lib/apache2/modules/libphp7.so

RUN a2enmod rewrite headers
RUN echo "date.timezone = \"Africa/Lagos\"" > /usr/local/etc/php/conf.d/timezone.ini
# Defence in depth for CVE-2026-109619 (php.net advisory); the app does not use upload progress.
RUN echo "session.upload_progress.enabled = 0" > /usr/local/etc/php/conf.d/security.ini

WORKDIR /app
COPY . /app
RUN ln -s /app/*[^public] /var/www && rm -rf /var/www/html && ln -nsf /app/public /var/www/html
RUN chmod -R 750 /app/public/uploads /app/application/logs && chown -R www-data:www-data /app/public /app/application
