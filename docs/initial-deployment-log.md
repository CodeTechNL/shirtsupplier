# Initial Deployment Log (2026-04-03)

Chronological record of issues encountered and resolved during the first deployment.

## Issue 1: Docker Image Platform Mismatch

**Symptom:** `CannotPullContainerError: image Manifest does not contain descriptor matching platform 'linux/amd64'`

**Root Cause:** Docker image was built natively on Apple Silicon (ARM), but Fargate runs x86_64.

**Fix:** Always build with `--platform linux/amd64`:
```bash
docker build --platform linux/amd64 -t <tag> .
```

## Issue 2: FrankenPHP Docker Tag Not Found

**Symptom:** Docker build failed — `dunglas/frankenphp:latest-php8.4` doesn't exist.

**Fix:** Changed to `dunglas/frankenphp:1-php8.4` (correct tag format).

## Issue 3: Missing intl Extension in Composer Stage

**Symptom:** `composer install` failed because Filament requires `ext-intl`.

**Fix:** Added `--ignore-platform-req=ext-intl` to the composer install command. The extension is installed in the production stage.

## Issue 4: Missing Storage Directories

**Symptom:** `Please provide a valid cache path` at runtime.

**Root Cause:** `.dockerignore` excludes `storage/framework/*` directories, and they weren't recreated at runtime.

**Fix:** Added directory creation to `docker/entrypoint.sh`:
```bash
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Issue 5: SSM Parameters Set to CHANGE_ME

**Symptom:** `getaddrinfo for CHANGE_ME failed` — DB_HOST was a placeholder.

**Root Cause:** SSM parameters were manually created with `CHANGE_ME` placeholder values.

**Fix:** Updated SSM parameters with real values:
- `/production/DB_HOST` → RDS endpoint (now managed by CDK/RdsStack)
- `/production/APP_KEY` → Generated via `php artisan key:generate --show`
- `/production/APP_URL` → `https://shirtsupplier-app.com`

## Issue 6: Cross-Stack Security Group Rule Not Created

**Symptom:** `SQLSTATE[HY000] [2002] Connection timed out` — Fargate couldn't reach RDS.

**Root Cause:** `props.databaseSecurityGroup.addIngressRule()` adds the rule to the RDS stack's CloudFormation template. Since the RDS stack had no changes, it wasn't redeployed and the rule was never created.

**Fix:** Replaced with explicit `CfnSecurityGroupIngress` in the PlatformStack:
```typescript
new CfnSecurityGroupIngress(this, "RdsIngressFromFargate", {
    ipProtocol: "tcp",
    fromPort: 3306,
    toPort: 3306,
    groupId: props.databaseSecurityGroup.securityGroupId,
    sourceSecurityGroupId: fargateSecurityGroup.securityGroupId,
});
```

## Issue 7: SSM Parameter /production/DB_HOST Already Exists

**Symptom:** `Resource of type 'AWS::SSM::Parameter' with identifier '/production/DB_HOST' already exists`

**Root Cause:** The parameter was manually created before CDK tried to create it in the RDS stack.

**Fix:** Deleted the manual SSM parameter, let CDK manage it:
```bash
aws ssm delete-parameter --name /production/DB_HOST --profile shirtsupplier --region eu-central-1
```

## Issue 8: FrankenPHP Port 443 Convention Violation

**Symptom:** `adapting config using caddyfile: [http://:443] scheme and port violate convention`

**Root Cause:** FrankenPHP/Caddy refuses to serve plain HTTP on port 443 (that port is reserved for HTTPS by convention).

**Fix:** Changed Octane to listen on port 80 instead of 443. Updated supervisord.conf, Dockerfile EXPOSE, CDK port mappings, and health check.

## Issue 9: Octane Admin Port Required

**Symptom:** `Unable to determine admin port. Please specify the [--admin-port] option.`

**Root Cause:** Laravel Octane v2 with FrankenPHP requires the `--admin-port` flag.

**Fix:** Added `--admin-port=2019` to the octane:start command in supervisord.conf.

## Issue 10: queue:listen vs queue:work

**Symptom:** `The "--max-time" option does not exist.`

**Root Cause:** `queue:listen` does not support `--max-time`. Only `queue:work` does.

**Fix:** Changed from `queue:listen` to `queue:work` in supervisord.conf.

## Issue 11: AWS SDK Not Installed

**Symptom:** `Class "Aws\Sqs\SqsClient" not found`

**Root Cause:** `aws/aws-sdk-php` was not in `composer.json`. The SQS queue driver requires it.

**Fix:** `composer require aws/aws-sdk-php`

## Issue 12: Capacity Provider Error

**Symptom:** `CapacityProviderStrategy is not supported on this cluster`

**Root Cause:** `enableFargateCapacityProviders: true` on the Cluster construct conflicted with the existing cluster.

**Fix:** Removed the flag. Capacity provider strategies are set on the FargateService directly.

## Issue 13: Docker Build Cache Serving Stale Layers

**Symptom:** Rebuilt image didn't contain recent file changes (e.g., updated supervisord.conf).

**Root Cause:** Docker's build cache served stale layers even though files changed.

**Fix:** Used `--no-cache` flag for rebuilds when changes weren't being picked up:
```bash
docker build --platform linux/amd64 --no-cache -t <tag> .
```

## Final Working Configuration

After all fixes:
- Octane runs on port 80 with `--admin-port=2019`
- Queue worker uses `queue:work` with `--max-time=3600`
- Migration failure is non-fatal (container still starts)
- Security group rule uses `CfnSecurityGroupIngress`
- ECR/LogGroup are imported, not created
- All SSM parameters populated with real values
- AWS SDK installed for SQS driver
- Docker images built for `linux/amd64`
