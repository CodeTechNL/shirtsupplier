#!/bin/bash
set -euo pipefail

STAGE="${1:?Usage: deploy.sh <stage>}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
CDK_DIR="${PROJECT_ROOT}/cdk"

# Determine AWS region from CDK config
AWS_REGION=$(node -e "
    const c = require('${CDK_DIR}/lib/config').CONFIG_PER_STAGE['${STAGE}'];
    process.stdout.write(c.env.region);
")

echo "Deploying stage: ${STAGE} to region: ${AWS_REGION}"

# Load /stage/* SSM parameters as process env vars
echo "Loading SSM parameters for /${STAGE}/..."
while IFS=$'\t' read -r name value; do
    var_name="${name##*/}"
    export "$var_name=$value"
    echo "  Loaded: ${var_name}"
done < <(aws ssm get-parameters-by-path \
    --path "/${STAGE}" \
    --with-decryption \
    --region "${AWS_REGION}" \
    --query "Parameters[*].[Name,Value]" \
    --output text)

# CDK deploy
echo "Deploying CDK stacks..."
cd "$CDK_DIR"
STAGE="$STAGE" npx cdk deploy --all --require-approval never

# Get stack outputs
STACK_NAME="${STAGE}-Platform"
get_output() {
    aws cloudformation describe-stacks \
        --stack-name "$STACK_NAME" \
        --region "$AWS_REGION" \
        --query "Stacks[0].Outputs[?OutputKey=='$1'].OutputValue" \
        --output text
}

ECR_URI=$(get_output "EcrRepositoryUri")
CLUSTER=$(get_output "ClusterName")
SERVICE=$(get_output "ServiceName")

echo "ECR: ${ECR_URI}"
echo "Cluster: ${CLUSTER}"
echo "Service: ${SERVICE}"

# Docker build + push to ECR
echo "Building and pushing Docker image..."
aws ecr get-login-password --region "$AWS_REGION" | \
    docker login --username AWS --password-stdin "${ECR_URI%%/*}"

GIT_SHA=$(git -C "$PROJECT_ROOT" rev-parse --short HEAD)
docker build -t "$ECR_URI:latest" -t "$ECR_URI:${GIT_SHA}" "$PROJECT_ROOT"
docker push "$ECR_URI:latest"
docker push "$ECR_URI:${GIT_SHA}"

# Force new ECS deployment
echo "Triggering ECS deployment..."
aws ecs update-service \
    --cluster "$CLUSTER" \
    --service "$SERVICE" \
    --force-new-deployment \
    --region "$AWS_REGION" \
    --no-cli-pager

echo "Deploy complete. Service is updating..."
echo "Monitor: aws ecs describe-services --cluster ${CLUSTER} --services ${SERVICE} --region ${AWS_REGION}"
