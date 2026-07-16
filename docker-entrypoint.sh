#!/bin/sh
set -e

# =============================================================================
# Laravel Docker Entrypoint Script for Railway
# FAST STARTUP - migrations run separately via release.sh
# =============================================================================

echo "🚀 Starting Laravel application..."

cd /var/www/html

# =============================================================================
# 1. Quick validation
# =============================================================================
if [ -z "${APP_KEY}" ]; then
    echo "❌ ERROR: APP_KEY environment variable is not set!"
    exit 1
fi

# =============================================================================
# 2. Create storage link (quick)
# =============================================================================
if [ ! -L "public/storage" ]; then
    php artisan storage:link --force 2>/dev/null || true
fi

# =============================================================================
# 3. Cache configuration (production optimization) - ~5 seconds
# =============================================================================
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# =============================================================================
# 4. Start server IMMEDIATELY
# =============================================================================
echo ""
echo "✅ Laravel ready! Starting server on port ${PORT:-8080}..."

exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
