# 使用PHP 8.3 FPM Alpine作为基础镜像
FROM php:8.3-fpm-alpine AS builder

# 设置工作目录
WORKDIR /app

# 安装构建依赖
RUN apk add --no-cache \
    # 系统工具
    curl \
    autoconf \
    gcc \
    g++ \
    make \
    # PHP扩展依赖 
    libpng-dev \
    libzip-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    # 额外工具
    zip \
    git

# 配置并安装PHP扩展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# 安装Redis扩展
RUN pecl install redis \
    && docker-php-ext-enable redis

# 配置opcache
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=60'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
 
# 复制项目文件
COPY . /app
# 在 builder 阶段
RUN composer require topthink/think-migration
# 优化Composer依赖
RUN composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts \
    --prefer-dist

# 下载 Tailwind CSS CLI 并构建生产 CSS
RUN curl -sL https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-linux-x64 -o /usr/local/bin/tailwindcss \
    && chmod +x /usr/local/bin/tailwindcss \
    && /usr/local/bin/tailwindcss -i /app/public/assets/css/tailwind-input.css -o /app/public/assets/css/tailwind.css --minify

# 调整PHP配置
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i \
        -e 's/memory_limit = 128M/memory_limit = 256M/g' \
        -e 's/max_execution_time = 30/max_execution_time = 60/g' \
        -e 's/upload_max_filesize = 2M/upload_max_filesize = 20M/g' \
        -e 's/post_max_size = 8M/post_max_size = 20M/g' \
        "$PHP_INI_DIR/php.ini"

# 准备运行时目录
RUN mkdir -p /app/runtime/log/ \
    && chmod -R 755 /app \
    && chown -R www-data:www-data /app \
    && rm -rf /docker/*

# 最终镜像
FROM php:8.3-fpm-alpine

# 安装运行时依赖
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng \
    libjpeg \
    freetype \
    libzip \
    tzdata \
    && cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
    && echo "Asia/Shanghai" > /etc/timezone

# 复制必要文件
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/etc/php/php.ini /usr/local/etc/php/php.ini
COPY --from=builder /app /app
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 复制配置文件
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/start.sh /start.sh
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /app/docker/supervisord.conf

# 设置权限和工作目录
WORKDIR /app
RUN chmod +x /start.sh \
    && chown -R www-data:www-data /app \
    && mkdir -p /var/run/nginx \
    && chmod -R 755 /app/runtime

# 暴露端口
EXPOSE 8018 2347 2348

# 健康检查 — 同时验证 Web 端点和 Queue Worker
HEALTHCHECK --interval=30s --timeout=5s \
    CMD wget --no-verbose --tries=1 --spider http://localhost:8018/ping && supervisorctl -c /app/docker/supervisord.conf status queue-worker | grep -q RUNNING || exit 1

# 启动命令
CMD ["/start.sh"]