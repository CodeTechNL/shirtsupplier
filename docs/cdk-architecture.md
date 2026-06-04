# CDK Architecture

## Stack Overview

```
cdk/bin/cdk.ts                  # Entry point — wires up all stacks
cdk/lib/RegionalStack.ts        # Base class with getDefaultVpc(), isProduction()
cdk/lib/stages.ts               # Type: StageName = "production"
cdk/lib/config.ts               # Per-stage configuration (account, region, stack props)
cdk/lib/env.ts                  # Environment variable helpers
cdk/lib/stacks/
  queue.ts                      # SQS Queue
  rds.ts                        # RDS MySQL + DB_HOST SSM parameter
  platform.ts                   # ECS Fargate + Lambda DNS + EventBridge
cdk/scripts/
  deploy.sh                     # Deployment script (loads env, fetches SSM, runs cdk deploy)
cdk/.env.production             # Static env vars (STAGE, AWS_ACCOUNT, AWS_REGION)
```

## Stack Dependencies

```
production-Queue ──────┐
                       ├──> production-Platform
production-Rds ────────┘
```

- **Platform** depends on Queue (needs `queue` reference for SQS_QUEUE env var + IAM)
- **Platform** depends on Rds (needs `databaseSecurityGroup` for SG ingress rule)
- **Rds** writes `DB_HOST` to SSM — Platform reads it at container runtime (avoids cyclic dependency)

### Cyclic Dependency Avoidance

The Platform stack needs the RDS endpoint address (`DB_HOST`), and it also adds a security group rule to the RDS security group. If Platform consumed `rdsStack.dbInstanceEndpointAddress` directly while also modifying RDS resources, CDK would detect a circular dependency.

**Solution:** RDS writes `DB_HOST` to SSM Parameter Store. Platform reads it via the ECS task's `secrets` configuration at container startup time. This decouples the two stacks.

## Key Design Decisions

### No ALB — Cloudflare for HTTPS

To minimize costs, the architecture skips the ALB (~$16/month) and assigns a public IP directly to the Fargate task. A Lambda + EventBridge combination auto-updates the Cloudflare DNS A record (proxied) when a task starts.

**HTTPS** is handled by Cloudflare's proxy (free tier). The Fargate container serves HTTP on port 80, and Cloudflare terminates TLS. This avoids cert management complexity and the Let's Encrypt chicken-and-egg problem on ephemeral containers.

### Imported ECR + LogGroup

The ECR repository and CloudWatch LogGroup are **imported** (not created) by the PlatformStack. This prevents "already exists" errors when the stack is deleted and recreated (these resources persist after stack deletion).

```typescript
const repository = Repository.fromRepositoryName(this, "Repository", `shirtsupplier-${this.stageName}`);
const logGroup = LogGroup.fromLogGroupName(this, "LogGroup", `/ecs/shirtsupplier-${this.stageName}`);
```

### CfnSecurityGroupIngress for Cross-Stack SG Rules

When a security group from Stack A is passed to Stack B, `addIngressRule()` adds the rule to Stack A's template. If Stack A has no changes, the rule is never deployed.

Using `CfnSecurityGroupIngress` explicitly creates the ingress rule as a CloudFormation resource in the Platform stack, ensuring it's always created.

### No enableFargateCapacityProviders on Cluster

Setting `enableFargateCapacityProviders: true` on the Cluster causes errors if the cluster already exists without this setting. Instead, capacity provider strategies are specified on the FargateService directly.

## Lambda DNS Updater (Cloudflare)

The Lambda is triggered by EventBridge when an ECS task reaches the `RUNNING` state. It:

1. Calls `DescribeTasks` to find the task's ENI
2. Calls `DescribeNetworkInterfaces` to get the public IP
3. Reads Cloudflare credentials from SSM (`/production/cloudflare/API_TOKEN`, `ZONE_ID`, `RECORD_ID`)
4. Calls Cloudflare API to update the A record with `proxied: true`

The Lambda code is inline in the CDK stack (no separate deployment artifact). Cloudflare params are cached in the Lambda execution context for warm invocations.

## Configuration

All configuration lives in `cdk/lib/config.ts`:

```typescript
production: {
    env: { account: "277328279708", region: "eu-central-1" },
    stacks: {
        platform: {
            cpu: 256,              // 0.25 vCPU
            memoryLimitMiB: 512,   // 512 MB
            desiredCount: 1,
            useSpot: false,
            domainName: "shirtsupplier-app.com",
        },
        rds: {
            databaseName: "shirt_supplier",
            instanceClass: InstanceClass.BURSTABLE3,  // db.t3
            instanceIdentifier: "production-db",
            instanceSize: InstanceSize.MICRO,          // .micro
        },
    },
}
```
