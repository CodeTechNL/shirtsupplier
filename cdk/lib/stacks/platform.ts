import {CfnOutput, Duration} from "aws-cdk-lib";
import {
    CfnSecurityGroupIngress,
    Peer,
    Port,
    SecurityGroup,
    SubnetType,
    type ISecurityGroup,
} from "aws-cdk-lib/aws-ec2";
import {
    Cluster,
    ContainerImage,
    CpuArchitecture,
    FargateService,
    FargateTaskDefinition,
    LogDrivers,
    OperatingSystemFamily,
    Secret,
} from "aws-cdk-lib/aws-ecs";
import {Repository} from "aws-cdk-lib/aws-ecr";
import {LogGroup} from "aws-cdk-lib/aws-logs";
import {type IQueue} from "aws-cdk-lib/aws-sqs";
import {StringParameter} from "aws-cdk-lib/aws-ssm";
import {Effect, PolicyStatement} from "aws-cdk-lib/aws-iam";
import {Rule} from "aws-cdk-lib/aws-events";
import {LambdaFunction} from "aws-cdk-lib/aws-events-targets";
import {Code, Function as LambdaFn, Runtime} from "aws-cdk-lib/aws-lambda";
import type {Construct} from "constructs";
import {RegionalStack, RegionalStackProps} from "../RegionalStack";

interface PlatformStackProps extends RegionalStackProps {
    readonly databaseSecurityGroup: ISecurityGroup;
    readonly queue: IQueue;
    readonly cpu: number;
    readonly memoryLimitMiB: number;
    readonly desiredCount: number;
    readonly useSpot: boolean;
    readonly domainName: string;
}

const SSM_PARAMETERS = [
    "APP_KEY",
    "APP_URL",
    "DB_HOST",
    "DB_USERNAME",
    "DB_PASSWORD",
    "LIGHTSPEED_API_KEY",
    "LIGHTSPEED_API_SECRET",
] as const;

export class PlatformStack extends RegionalStack {
    readonly applicationLogGroupName: string;

    constructor(scope: Construct, id: string, props: PlatformStackProps) {
        super(scope, id, props);

        const vpc = this.getDefaultVpc();

        // ECR Repository
        const repository = Repository.fromRepositoryName(
            this,
            "Repository",
            `shirtsupplier-${this.stageName}`,
        );

        // ECS Cluster
        const cluster = new Cluster(this, "Cluster", {
            vpc,
            clusterName: `shirtsupplier-${this.stageName}`,
        });

        // CloudWatch Log Group
        const logGroup = LogGroup.fromLogGroupName(
            this,
            "LogGroup",
            `/ecs/shirtsupplier-${this.stageName}`,
        );
        this.applicationLogGroupName = `/ecs/shirtsupplier-${this.stageName}`;

        // Task Definition
        const taskDefinition = new FargateTaskDefinition(this, "TaskDef", {
            cpu: props.cpu,
            memoryLimitMiB: props.memoryLimitMiB,
            runtimePlatform: {
                cpuArchitecture: CpuArchitecture.X86_64,
                operatingSystemFamily: OperatingSystemFamily.LINUX,
            },
        });

        // SSM Parameter Store secrets
        const secrets: Record<string, Secret> = {};
        for (const param of SSM_PARAMETERS) {
            secrets[param] = Secret.fromSsmParameter(
                StringParameter.fromStringParameterName(
                    this,
                    `Param-${param}`,
                    `/${this.stageName}/${param}`,
                ),
            );
        }

        // Container
        taskDefinition.addContainer("app", {
            image: ContainerImage.fromEcrRepository(repository),
            logging: LogDrivers.awsLogs({
                logGroup,
                streamPrefix: "app",
            }),
            secrets,
            environment: {
                APP_ENV: this.stageName,
                APP_DEBUG: this.isProduction() ? "false" : "true",
                LOG_CHANNEL: "stderr",
                CACHE_STORE: "file",
                SESSION_DRIVER: "cookie",
                DB_CONNECTION: "mysql",
                DB_PORT: "3306",
                DB_DATABASE: "shirt_supplier",
                QUEUE_CONNECTION: "sqs",
                SQS_PREFIX: `https://sqs.${props.env!.region}.amazonaws.com/${props.env!.account}`,
                SQS_QUEUE: props.queue.queueName,
                LIGHTSPEED_API_CLUSTER: "eu1",
                LIGHTSPEED_API_DEFAULT_LANGUAGE: "nl",
            },
            portMappings: [
                {containerPort: 80},
            ],
            healthCheck: {
                command: ["CMD-SHELL", "curl -f http://localhost:80/up || exit 1"],
                interval: Duration.seconds(30),
                timeout: Duration.seconds(5),
                retries: 3,
                startPeriod: Duration.seconds(60),
            },
        });

        // Security Group for Fargate tasks
        const fargateSecurityGroup = new SecurityGroup(this, "FargateSG", {
            vpc,
            description: "Security group for Fargate tasks",
            allowAllOutbound: true,
        });
        fargateSecurityGroup.addIngressRule(Peer.anyIpv4(), Port.tcp(80), "Allow HTTP");
        fargateSecurityGroup.addIngressRule(Peer.anyIpv4(), Port.tcp(443), "Allow HTTPS");

        // Allow Fargate → RDS on port 3306
        new CfnSecurityGroupIngress(this, "RdsIngressFromFargate", {
            ipProtocol: "tcp",
            fromPort: 3306,
            toPort: 3306,
            groupId: props.databaseSecurityGroup.securityGroupId,
            sourceSecurityGroupId: fargateSecurityGroup.securityGroupId,
            description: "Allow Fargate tasks to connect to RDS",
        });

        // ECS Service (no ALB)
        const service = new FargateService(this, "Service", {
            cluster,
            taskDefinition,
            serviceName: `shirtsupplier-${this.stageName}`,
            desiredCount: props.desiredCount,
            assignPublicIp: true,
            vpcSubnets: {subnetType: SubnetType.PUBLIC},
            securityGroups: [fargateSecurityGroup],
            capacityProviderStrategies: props.useSpot
                ? [{capacityProvider: "FARGATE_SPOT", weight: 1}]
                : [{capacityProvider: "FARGATE", weight: 1}],
            circuitBreaker: {enable: true, rollback: true},
            minHealthyPercent: 0,
            maxHealthyPercent: 100,
        });

        // IAM: SQS permissions for task role
        props.queue.grantConsumeMessages(taskDefinition.taskRole);
        props.queue.grantSendMessages(taskDefinition.taskRole);

        // IAM: SSM Parameter Store permissions for execution role
        taskDefinition.executionRole?.addToPrincipalPolicy(
            new PolicyStatement({
                effect: Effect.ALLOW,
                actions: ["ssm:GetParameters", "ssm:GetParameter"],
                resources: [
                    `arn:aws:ssm:${props.env!.region}:${props.env!.account}:parameter/${this.stageName}/*`,
                ],
            }),
        );

        // Cloudflare SSM parameters
        const cloudflareApiToken = StringParameter.fromStringParameterName(
            this,
            "CloudflareApiToken",
            `/${this.stageName}/cloudflare/API_TOKEN`,
        );
        const cloudflareZoneId = StringParameter.fromStringParameterName(
            this,
            "CloudflareZoneId",
            `/${this.stageName}/cloudflare/ZONE_ID`,
        );
        const cloudflareRecordId = StringParameter.fromStringParameterName(
            this,
            "CloudflareRecordId",
            `/${this.stageName}/cloudflare/RECORD_ID`,
        );

        // Lambda DNS Updater (Cloudflare)
        const dnsUpdaterFn = new LambdaFn(this, "DnsUpdater", {
            runtime: Runtime.NODEJS_22_X,
            handler: "index.handler",
            code: Code.fromInline(`
const { ECSClient, DescribeTasksCommand } = require("@aws-sdk/client-ecs");
const { EC2Client, DescribeNetworkInterfacesCommand } = require("@aws-sdk/client-ec2");
const { SSMClient, GetParameterCommand } = require("@aws-sdk/client-ssm");

const ecs = new ECSClient();
const ec2 = new EC2Client();
const ssm = new SSMClient();

let cachedParams = null;

async function getCloudflareParams() {
    if (cachedParams) return cachedParams;
    const [apiToken, zoneId, recordId] = await Promise.all([
        ssm.send(new GetParameterCommand({ Name: process.env.SSM_CF_API_TOKEN, WithDecryption: true })),
        ssm.send(new GetParameterCommand({ Name: process.env.SSM_CF_ZONE_ID })),
        ssm.send(new GetParameterCommand({ Name: process.env.SSM_CF_RECORD_ID })),
    ]);
    cachedParams = {
        apiToken: apiToken.Parameter.Value,
        zoneId: zoneId.Parameter.Value,
        recordId: recordId.Parameter.Value,
    };
    return cachedParams;
}

exports.handler = async (event) => {
    const detail = event.detail;
    if (detail.lastStatus !== "RUNNING") return;

    const clusterArn = detail.clusterArn;
    const taskArn = detail.taskArn;

    const taskResponse = await ecs.send(new DescribeTasksCommand({
        cluster: clusterArn,
        tasks: [taskArn],
    }));

    const task = taskResponse.tasks?.[0];
    if (!task) return;

    const eniAttachment = task.attachments?.find(a => a.type === "ElasticNetworkInterface");
    const eniId = eniAttachment?.details?.find(d => d.name === "networkInterfaceId")?.value;
    if (!eniId) return;

    const eniResponse = await ec2.send(new DescribeNetworkInterfacesCommand({
        NetworkInterfaceIds: [eniId],
    }));

    const publicIp = eniResponse.NetworkInterfaces?.[0]?.Association?.PublicIp;
    if (!publicIp) return;

    const cf = await getCloudflareParams();

    const response = await fetch(
        \`https://api.cloudflare.com/client/v4/zones/\${cf.zoneId}/dns_records/\${cf.recordId}\`,
        {
            method: "PUT",
            headers: {
                "Authorization": \`Bearer \${cf.apiToken}\`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                type: "A",
                name: process.env.DOMAIN_NAME,
                content: publicIp,
                proxied: true,
                ttl: 1,
            }),
        }
    );

    const result = await response.json();
    if (!result.success) {
        console.error("Cloudflare API error:", JSON.stringify(result.errors));
        throw new Error("Failed to update Cloudflare DNS");
    }

    console.log("Updated Cloudflare DNS: " + process.env.DOMAIN_NAME + " -> " + publicIp + " (proxied)");
};
`),
            environment: {
                DOMAIN_NAME: props.domainName,
                SSM_CF_API_TOKEN: `/${this.stageName}/cloudflare/API_TOKEN`,
                SSM_CF_ZONE_ID: `/${this.stageName}/cloudflare/ZONE_ID`,
                SSM_CF_RECORD_ID: `/${this.stageName}/cloudflare/RECORD_ID`,
            },
            timeout: Duration.seconds(30),
        });

        // Lambda IAM permissions
        dnsUpdaterFn.addToRolePolicy(
            new PolicyStatement({
                effect: Effect.ALLOW,
                actions: ["ssm:GetParameter"],
                resources: [
                    cloudflareApiToken.parameterArn,
                    cloudflareZoneId.parameterArn,
                    cloudflareRecordId.parameterArn,
                ],
            }),
        );
        dnsUpdaterFn.addToRolePolicy(
            new PolicyStatement({
                effect: Effect.ALLOW,
                actions: ["ecs:DescribeTasks"],
                resources: ["*"],
            }),
        );
        dnsUpdaterFn.addToRolePolicy(
            new PolicyStatement({
                effect: Effect.ALLOW,
                actions: ["ec2:DescribeNetworkInterfaces"],
                resources: ["*"],
            }),
        );

        // EventBridge rule: trigger Lambda when ECS task starts
        new Rule(this, "EcsTaskRunningRule", {
            eventPattern: {
                source: ["aws.ecs"],
                detailType: ["ECS Task State Change"],
                detail: {
                    clusterArn: [cluster.clusterArn],
                    lastStatus: ["RUNNING"],
                },
            },
            targets: [new LambdaFunction(dnsUpdaterFn)],
        });

        // Outputs
        new CfnOutput(this, "EcrRepositoryUri", {
            value: repository.repositoryUri,
        });
        new CfnOutput(this, "ClusterName", {
            value: cluster.clusterName,
        });
        new CfnOutput(this, "ServiceName", {
            value: service.serviceName,
        });
    }
}
