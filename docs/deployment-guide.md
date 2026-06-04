# Deployment Guide

## Overview

The shirtsupplier application runs on AWS Fargate (ECS) without an ALB. A Lambda function auto-updates the Route 53 A-record when a new Fargate task starts, and FrankenPHP (via Laravel Octane) handles HTTP traffic directly.

## Architecture

```
Internet -> Route 53 (shirtsupplier-app.com) -> Fargate Public IP:80
                                                  |
                                                  +-> Octane/FrankenPHP (port 80)
                                                  +-> Queue Worker (SQS)
                                                  +-> RDS MySQL (via Security Group)
```

### Components

| Component | Details |
|-----------|---------|
| **Compute** | AWS Fargate (256 CPU / 512 MB) |
| **Web Server** | FrankenPHP via Laravel Octane on port 80 |
| **Queue** | SQS + `queue:work` via supervisord |
| **Database** | RDS MySQL 8.4 (db.t3.micro) |
| **DNS** | Route 53 + Lambda auto-updater |
| **Container Registry** | ECR (`shirtsupplier-production`) |
| **Process Manager** | supervisord (runs Octane + queue worker) |
| **Region** | eu-central-1 (Frankfurt) |
| **AWS Account** | 277328279708 |
| **AWS Profile** | shirtsupplier |

### CDK Stacks

1. **production-Queue** - SQS queue
2. **production-Rds** - RDS MySQL instance + security group + DB_HOST SSM parameter
3. **production-Platform** - ECS Cluster, Fargate Service, Task Definition, Lambda DNS updater, EventBridge rule, Security Groups

## Prerequisites

- AWS CLI configured with profile `shirtsupplier`
- Docker Desktop running (for building amd64 images on Apple Silicon)
- Node.js + npm (for CDK)
- CDK CLI installed (`npm install -g aws-cdk`)

## Deploy Commands

### Full deployment (infrastructure + application)

```bash
./cdk/scripts/deploy.sh production --require-approval never
```

This script:
1. Loads environment from `cdk/.env.production`
2. Fetches DB_PASSWORD and DB_USERNAME from SSM
3. Runs `cdk deploy --all`

### Application-only deployment (new Docker image)

```bash
# 1. Build for linux/amd64 (REQUIRED on Apple Silicon Macs)
docker build --platform linux/amd64 -t 277328279708.dkr.ecr.eu-central-1.amazonaws.com/shirtsupplier-production:latest .

# 2. Login to ECR
aws ecr get-login-password --region eu-central-1 --profile shirtsupplier | \
  docker login --username AWS --password-stdin 277328279708.dkr.ecr.eu-central-1.amazonaws.com

# 3. Push image
docker push 277328279708.dkr.ecr.eu-central-1.amazonaws.com/shirtsupplier-production:latest

# 4. Force new deployment (pulls the latest image)
aws ecs update-service \
  --cluster shirtsupplier-production \
  --service shirtsupplier-production \
  --force-new-deployment \
  --profile shirtsupplier \
  --region eu-central-1
```

### Verify deployment

```bash
# Check service health
aws ecs describe-services \
  --cluster shirtsupplier-production \
  --services shirtsupplier-production \
  --profile shirtsupplier \
  --region eu-central-1 \
  --query "services[0].{status:status,running:runningCount,desired:desiredCount}"

# Check app health
curl -f http://shirtsupplier-app.com/up

# Check container logs
STREAM=$(aws logs describe-log-streams \
  --log-group-name /ecs/shirtsupplier-production \
  --order-by LastEventTime --descending --limit 1 \
  --query "logStreams[0].logStreamName" --output text \
  --profile shirtsupplier --region eu-central-1)

aws logs get-log-events \
  --log-group-name /ecs/shirtsupplier-production \
  --log-stream-name "$STREAM" \
  --limit 50 --profile shirtsupplier --region eu-central-1 \
  --query "events[*].message" --output json
```

## SSM Parameters

All secrets are stored in SSM Parameter Store under the `/{stage}/` prefix.

| Parameter | Type | Managed By |
|-----------|------|------------|
| `/production/APP_KEY` | String | Manual (generated via `php artisan key:generate --show`) |
| `/production/APP_URL` | String | Manual (`https://shirtsupplier-app.com`) |
| `/production/DB_HOST` | String | CDK (RdsStack writes the RDS endpoint) |
| `/production/DB_USERNAME` | String | Manual |
| `/production/DB_PASSWORD` | String | Manual |
| `/production/LIGHTSPEED_API_KEY` | String | Manual |
| `/production/LIGHTSPEED_API_SECRET` | String | Manual |

**Important:** Do NOT manually create `/production/DB_HOST` — CDK manages it. If it already exists before deploying the RDS stack, the deployment will fail with "already exists".

## Environment Variables (non-secret)

These are set directly in the CDK task definition (not SSM):

```
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
CACHE_STORE=file
SESSION_DRIVER=cookie
DB_CONNECTION=mysql
DB_PORT=3306
DB_DATABASE=shirt_supplier
QUEUE_CONNECTION=sqs
SQS_PREFIX=https://sqs.eu-central-1.amazonaws.com/277328279708
SQS_QUEUE=<queue-name-from-stack>
LIGHTSPEED_API_CLUSTER=eu1
LIGHTSPEED_API_DEFAULT_LANGUAGE=nl
```
