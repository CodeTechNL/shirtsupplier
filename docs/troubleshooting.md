# Troubleshooting

## Common Deployment Failures

### 1. ECS Circuit Breaker Triggered

**Error:** `Error occurred during operation 'ECS Deployment Circuit Breaker was triggered'`

This means the Fargate task keeps crashing. Check the container logs:

```bash
STREAM=$(aws logs describe-log-streams \
  --log-group-name /ecs/shirtsupplier-production \
  --order-by LastEventTime --descending --limit 1 \
  --query "logStreams[0].logStreamName" --output text \
  --profile shirtsupplier --region eu-central-1)

aws logs get-log-events \
  --log-group-name /ecs/shirtsupplier-production \
  --log-stream-name "$STREAM" --limit 50 \
  --profile shirtsupplier --region eu-central-1 \
  --query "events[*].message" --output json
```

Also check the task stop reason:

```bash
TASK=$(aws ecs list-tasks --cluster shirtsupplier-production \
  --desired-status STOPPED --profile shirtsupplier --region eu-central-1 \
  --query "taskArns[0]" --output text)

aws ecs describe-tasks --cluster shirtsupplier-production \
  --tasks "$TASK" --profile shirtsupplier --region eu-central-1 \
  --query "tasks[0].{stopCode:stopCode,stoppedReason:stoppedReason,containers:containers[*].{exitCode:exitCode,reason:reason}}"
```

**Common causes and fixes:**

| Log Message | Cause | Fix |
|-------------|-------|-----|
| `image Manifest does not contain descriptor matching platform 'linux/amd64'` | Image built for ARM (Apple Silicon) | Rebuild with `--platform linux/amd64` |
| `Please provide a valid cache path` | Storage directories missing | Entrypoint must create `storage/framework/{sessions,views,cache}` |
| `SQLSTATE[HY000] [2002] Connection timed out` | Fargate SG can't reach RDS | Check SG ingress rule (see #3 below) |
| `SQLSTATE[HY000] [2002] getaddrinfo for CHANGE_ME failed` | SSM parameter not set | Update `/production/DB_HOST` in SSM |
| `Unable to determine admin port` | Octane v2 needs `--admin-port` | Add `--admin-port=2019` to octane:start command |
| `scheme and port violate convention` | Trying HTTP on port 443 | Use port 80, not 443 (unless configuring TLS properly) |
| `Class "Aws\Sqs\SqsClient" not found` | AWS SDK not installed | Run `composer require aws/aws-sdk-php` |
| `The "--max-time" option does not exist` | Using `queue:listen` instead of `queue:work` | Change to `queue:work` (supports `--max-time`) |

### 2. Stack in ROLLBACK_COMPLETE State

A `ROLLBACK_COMPLETE` stack cannot be updated. You must delete it first:

```bash
aws cloudformation delete-stack \
  --stack-name production-Platform \
  --region eu-central-1 \
  --profile shirtsupplier

aws cloudformation wait stack-delete-complete \
  --stack-name production-Platform \
  --region eu-central-1 \
  --profile shirtsupplier
```

Then redeploy.

### 3. Security Group: Fargate Cannot Reach RDS

The RDS security group ingress rule is managed by the PlatformStack using `CfnSecurityGroupIngress`. If you need to debug:

```bash
# Check RDS security group rules
SG_ID=$(aws rds describe-db-instances \
  --db-instance-identifier production-db \
  --profile shirtsupplier --region eu-central-1 \
  --query "DBInstances[0].VpcSecurityGroups[0].VpcSecurityGroupId" --output text)

aws ec2 describe-security-groups \
  --group-ids "$SG_ID" \
  --profile shirtsupplier --region eu-central-1 \
  --query "SecurityGroups[0].IpPermissions"
```

**Why `CfnSecurityGroupIngress` instead of `addIngressRule`:**
When the RDS security group is passed from the RDS stack to the Platform stack, `addIngressRule()` tries to add the rule in the source stack (RDS). Since the RDS stack has no changes, it doesn't deploy, and the rule never gets created. Using `CfnSecurityGroupIngress` explicitly creates the rule in the Platform stack's CloudFormation template.

### 4. SSM Parameter Already Exists

**Error:** `Resource of type 'AWS::SSM::Parameter' with identifier '/production/DB_HOST' already exists`

The RDS stack creates `/production/DB_HOST` automatically. If you manually created it:

```bash
aws ssm delete-parameter \
  --name /production/DB_HOST \
  --profile shirtsupplier \
  --region eu-central-1
```

Then redeploy. Let CDK manage this parameter.

### 5. ECR Repository or LogGroup Already Exists

Both are imported (not created) by the PlatformStack:

```typescript
const repository = Repository.fromRepositoryName(this, "Repository", `shirtsupplier-${this.stageName}`);
const logGroup = LogGroup.fromLogGroupName(this, "LogGroup", `/ecs/shirtsupplier-${this.stageName}`);
```

If they don't exist yet, create them manually before the first deploy:

```bash
aws ecr create-repository \
  --repository-name shirtsupplier-production \
  --profile shirtsupplier --region eu-central-1

aws logs create-log-group \
  --log-group-name /ecs/shirtsupplier-production \
  --profile shirtsupplier --region eu-central-1
```

### 6. Docker Build Doesn't Pick Up File Changes

Docker layer caching can sometimes serve stale layers. Verify the image contains your changes:

```bash
docker run --rm --platform linux/amd64 \
  277328279708.dkr.ecr.eu-central-1.amazonaws.com/shirtsupplier-production:latest \
  cat /etc/supervisor/conf.d/supervisord.conf
```

If changes are missing, rebuild with `--no-cache`:

```bash
docker build --platform linux/amd64 --no-cache -t <image-tag> .
```

### 7. ECS Service Not Pulling New Image

After pushing a new `:latest` image to ECR, existing tasks won't automatically update. Force a new deployment:

```bash
aws ecs update-service \
  --cluster shirtsupplier-production \
  --service shirtsupplier-production \
  --force-new-deployment \
  --profile shirtsupplier \
  --region eu-central-1
```

### 8. Capacity Provider Error

**Error:** `CapacityProviderStrategy is not supported on this cluster`

Do NOT set `enableFargateCapacityProviders: true` on the Cluster construct. The PlatformStack uses `capacityProviderStrategies` on the FargateService instead, which works without this flag.

### 9. CDK CLI Version Mismatch

**Error:** `Cloud assembly schema version X.Y.Z is not supported`

Update the CDK CLI:

```bash
cd cdk && npm install aws-cdk@latest
```

## Useful Debug Commands

```bash
# ECS service events (last 5)
aws ecs describe-services \
  --cluster shirtsupplier-production \
  --services shirtsupplier-production \
  --profile shirtsupplier --region eu-central-1 \
  --query "services[0].events[:5].message"

# Current DNS record
aws route53 list-resource-record-sets \
  --hosted-zone-id Z096864437ZF4QSEXJAA \
  --profile shirtsupplier \
  --query "ResourceRecordSets[?Name=='shirtsupplier-app.com.']"

# Running task's public IP
TASK=$(aws ecs list-tasks --cluster shirtsupplier-production \
  --desired-status RUNNING --profile shirtsupplier --region eu-central-1 \
  --query "taskArns[0]" --output text)

aws ecs describe-tasks --cluster shirtsupplier-production \
  --tasks "$TASK" --profile shirtsupplier --region eu-central-1 \
  --query "tasks[0].attachments[0].details[?name=='networkInterfaceId'].value" --output text

# Check all SSM parameters
aws ssm get-parameters \
  --names /production/APP_KEY /production/APP_URL /production/DB_HOST \
         /production/DB_USERNAME /production/DB_PASSWORD \
         /production/LIGHTSPEED_API_KEY /production/LIGHTSPEED_API_SECRET \
  --with-decryption --profile shirtsupplier --region eu-central-1 \
  --query "Parameters[*].{Name:Name,Value:Value}" --output table

# DNS Lambda logs (find latest log group)
aws logs describe-log-groups \
  --log-group-name-prefix "/aws/lambda/production-Platform-DnsUpdater" \
  --profile shirtsupplier --region eu-central-1 \
  --query "logGroups[-1].logGroupName" --output text
```
