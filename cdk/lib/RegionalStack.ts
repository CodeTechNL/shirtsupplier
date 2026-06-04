import path from "node:path";
import {CfnOutput, Duration, type Environment, RemovalPolicy, Stack, type StackProps} from "aws-cdk-lib";
import {type ISecurityGroup, type ISubnet, type IVpc, SecurityGroup, Vpc} from "aws-cdk-lib/aws-ec2";
import {RetentionDays} from "aws-cdk-lib/aws-logs";
import type {Construct} from "constructs";
import {ParameterTier, StringParameter} from "aws-cdk-lib/aws-ssm";
import { StageName } from "./stages";

export interface RegionalStackProps extends StackProps {
    readonly env: Environment; // Environment is set to required instead of optional.
    readonly stageName: StageName;
}

export abstract class RegionalStack extends Stack {
    protected readonly stageName: StageName;
    private defaultVpc?: IVpc;
    private readonly projectRoot: string;

    constructor(scope: Construct, id: string, props: RegionalStackProps) {
        const {stageName, ...stackProps} = props;

        super(scope, id, stackProps);

        this.stageName = stageName;
    }

    protected getDefaultVpc(): IVpc {
        if (!this.defaultVpc) {
            this.defaultVpc = Vpc.fromLookup(this, "Default-VPC", {
                isDefault: true,
            });
        }
        return this.defaultVpc;
    }

    protected getBackupRetention(): Duration {
        return this.isTest() ? Duration.days(7) : Duration.days(35);
    }

    protected getDeletionProtection(): boolean {
        return this.stageName === "production";
    }

    protected getRemovalPolicy(): RemovalPolicy {
        return this.isTest() ? RemovalPolicy.DESTROY : RemovalPolicy.RETAIN;
    }

    protected getRetention(): RetentionDays {
        return this.isTest() ? RetentionDays.ONE_WEEK : RetentionDays.THREE_MONTHS;
    }

    protected getSubnet(): ISubnet {
        const vpc = this.getDefaultVpc();
        const subnet = vpc.publicSubnets[0];

        if (subnet) {
            return subnet;
        }

        throw new Error("Default VPC has no public subnets");
    }

    protected getSubnetId(): string {
        return this.getSubnet().subnetId;
    }

    protected getVpcId(): string {
        return this.getDefaultVpc().vpcId;
    }

    protected isTest(): boolean {
        return !this.isProduction();
    }

    protected isProduction(): boolean {
        return this.stageName === "production";
    }
}
