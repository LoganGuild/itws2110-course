# ONE image for everything in week 3: the PHP app under Apache, PHPUnit, and
# Playwright.
#
# Start from Microsoft's Playwright image (Ubuntu 24.04 + Node + Chromium,
# Firefox, WebKit) and add PHP and Apache to it. Ubuntu 24.04 ships PHP 8.3.
# libapache2-mod-php8.3 is the same "PHP inside Apache" arrangement as the
# php:8.3-apache image from Homework 1.
FROM mcr.microsoft.com/playwright:v1.62.1-noble

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      apache2 libapache2-mod-php8.3 \
      php8.3-cli php8.3-sqlite3 php8.3-xml php8.3-mbstring php8.3-curl unzip \
 && rm -rf /var/lib/apt/lists/*

# Composer is PHP's npm. Copy the binary straight out of its official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Our one Apache site (see the file for what it does), replacing the default.
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /app

# Install PHPUnit (composer) and Playwright's test runner (npm) into the image,
# so nothing is downloaded when a test runs.
COPY composer.json package.json ./
RUN composer install --no-interaction --no-progress \
 && npm install --silent

COPY . .

# The SQLite file lives here. Apache's workers run as www-data, so the folder
# must belong to www-data -- and a named volume mounted here inherits that.
# (Same trick as the week-2 guestbook.)
RUN mkdir -p /app/data && chown www-data:www-data /app/data

EXPOSE 80

# Default: Apache in the foreground, which is how a server runs in a container.
CMD ["apache2ctl", "-D", "FOREGROUND"]
