#!/bin/sh
set -e

echo "[$(date)] Starting initialization..."

# 确保目录权限正确
echo "Setting up permissions..."
mkdir -p /app/runtime/log/

# 更改除 /app/.env 的文件权限
find /app -path /app/.env -prune -o -exec chown www-data:www-data {} \;

# 读取 .env 文件并导出环境变量
if [ -f /app/.env ]; then
    echo "Loading environment variables from .env file..."
    # 逐行读取 .env 以避免 xargs 空格/特殊字符截断
    while IFS= read -r line; do
        key="${line%%=*}"
        key="${key#"${key%%[![:space:]]*}"}"
        key="${key%"${key##*[![:space:]]}"}"
        case "$key" in ''|\#*) continue ;; esac
        value="${line#*=}"
        value="${value# }"
        value="${value#"${value%%[![:space:]]*}"}"
        value="${value%"${value##*[![:space:]]}"}"
        value=$(echo "$value" | sed -e 's/^["\x27]//' -e 's/["\x27]$//')
        export "${key}=${value}"
    done < /app/.env
else
    echo ".env file not found, skipping environment variable loading"
fi

chmod -R 755 /app/runtime

# 运行数据库迁移
# 检查是否存在迁移文件
if [ -d "/app/database/migrations" ] && [ "$(ls -A /app/database/migrations)" ]; then
    echo "Running database migrations..."
    php think migrate:run || echo "Migration skipped (tables may already exist)"
else
    echo "No migrations found, skipping migration step"
fi

# 启动PHP-FPM
echo "Starting PHP-FPM..."
php-fpm -D

# 启动 Supervisor 管理 Queue Worker
echo "Starting Supervisor..."
mkdir -p /app/runtime/log
supervisord -c /app/docker/supervisord.conf &
sleep 5
if ! supervisorctl -c /app/docker/supervisord.conf status queue-worker | grep -q RUNNING; then
    echo "ERROR: Supervisor queue-worker failed to start"
    exit 1
fi

# 启动Nginx
echo "Starting Nginx..."
nginx -g "daemon on;" &

# 启动GatewayWorker
echo "Starting GatewayWorker..."
php /app/server.php start





