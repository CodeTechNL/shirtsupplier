#!/usr/bin/env bash
set -euo pipefail

AWS_PROFILE="shirtsupplier"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CDK_DIR="$(dirname "$SCRIPT_DIR")"

STAGE="${1:?Usage: deploy.sh <stage> [cdk args...]}"
shift

ENV_FILE="$CDK_DIR/.env.${STAGE}"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Error: Environment file not found: $ENV_FILE"
    echo "Valid stages: production, acceptance, development"
    exit 1
fi

echo "Loading environment from $ENV_FILE..."
source "$ENV_FILE"

SSM_PREFIX="/${STAGE}"

echo "Fetching SSM parameters from ${SSM_PREFIX}..."

load_ssm_param() {
    local param_name="$1"
    local env_var="$2"

    local value
    value=$(aws ssm get-parameter \
        --name "${SSM_PREFIX}/${param_name}" \
        --with-decryption \
        --query "Parameter.Value" \
        --output text \
        --profile "$AWS_PROFILE" \
        --region "$AWS_REGION" 2>/dev/null) || {
        echo "Warning: SSM parameter ${SSM_PREFIX}/${param_name} not found, skipping."
        return 0
    }

    export "$env_var"="$value"
    echo "Loaded ${env_var} from SSM."
}

load_ssm_param "DB_PASSWORD" "DB_PASSWORD"
load_ssm_param "DB_USERNAME" "DB_USERNAME"

echo ""
echo "Deploying stage: $STAGE"
echo "Account: $AWS_ACCOUNT"
echo "Region: $AWS_REGION"
echo ""

cd "$CDK_DIR"
AWS_PROFILE="$AWS_PROFILE" cdk deploy --all "$@"
