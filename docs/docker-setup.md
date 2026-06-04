# Docker Setup

## Image Architecture

The Docker image uses a multi-stage build:

1. **vendor** (composer:2) - Installs PHP dependencies
2. **frontend** (node:22-alpine) - Builds frontend assets with Vite
3. **production** (dunglas/frankenphp:1-php8.4) - Final production image

### PHP Extensions Installed

- `intl` (required by Filament)
- `pcntl` (required by Octane)
- `pdo_mysql` (database driver)
- `redis` (cache driver)
- `opcache` (performance)

### System Packages

- `supervisor` (process manager)
- `curl` (health checks)

## Building the Image

**Always build with `--platform linux/amd64`** on Apple Silicon Macs. Fargate runs x86_64 containers.

```bash
# Standard build
docker build --platform linux/amd64 -t 277328279708.dkr.ecr.eu-central-1.amazonaws.com/shirtsupplier-production:latest .

# Force rebuild (no cache) — use when changes aren't picked up
docker build --platform linux/amd64 --no-cache -t 277328279708.dkr.ecr.eu-central-1.amazonaws.com/shirtsupplier-production:latest .
```

### Build Times (Apple Silicon via QEMU)

- **With cache**: ~30-60 seconds
- **Without cache**: ~10-15 minutes (PHP extension compilation is slow under emulation)

### Verifying the Image

```bash
# Check architecture
docker inspect --format='{{.Architecture}}' 277328279708.dkr.ecr.eu-central-1.amazonaws.com/shirtsupplier-production:latest
# Expected: amd64

# Check supervisord config
docker run --rm --platform linux/amd64 277328279708.dkr.ecr.eu-central-1.amazonaws.com/shirtsupplier-production:latest cat /etc/supervisor/conf.d/supervisord.conf

# Check entrypoint
docker run --rm --platform linux/amd64 277328279708.dkr.ecr.eu-central-1.amazonaws.com/shirtsupplier-production:latest cat /app/docker/entrypoint.sh
```

## Container Startup Sequence

The `docker/entrypoint.sh` runs in this order:

1. Create storage directories (`storage/framework/sessions`, `views`, `cache`, `logs`, `bootstrap/cache`)
2. Set ownership/permissions (`www-data:www-data`, `775`)
3. `php artisan config:cache`
4. `php artisan route:cache`
5. `php artisan view:cache`
6. `php artisan migrate --force` (non-fatal — continues on failure)
7. Start supervisord (Octane + queue worker)

## Supervisord Processes

| Process | Command | Notes |
|---------|---------|-------|
| **octane** | `php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=80 --admin-port=2019` | `--admin-port=2019` is required by Octane v2 |
| **queue-worker** | `php artisan queue:work sqs --sleep=3 --tries=3 --timeout=60 --max-time=3600` | Restarts every hour. Uses `queue:work` not `queue:listen` |

### Key Differences: queue:work vs queue:listen

- `queue:work`: Processes jobs in the current process. Supports `--max-time`, `--max-jobs`. More efficient.
- `queue:listen`: Spawns a new process per job. Does NOT support `--max-time`. Less efficient, use only when you need code reloading.

## .dockerignore

The following are excluded from the Docker build context:

- `node_modules/`, `vendor/` (built inside Docker)
- `.git/`, `cdk/`, `tests/`
- `.env`, `.env.*`
- `storage/logs/`, `storage/framework/cache/`, `storage/framework/sessions/`, `storage/framework/views/`
- `bootstrap/cache/`

**Important:** Because storage directories are excluded, the entrypoint.sh must recreate them at runtime.

## Required Composer Packages

- `aws/aws-sdk-php` - Required for SQS queue driver. Without it: `Class "Aws\Sqs\SqsClient" not found`.
