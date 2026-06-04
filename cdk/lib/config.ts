import {InstanceClass, InstanceSize} from "aws-cdk-lib/aws-ec2";
import {StageName} from "./stages";

export type ConfigPerStage = {
    /**
     * AWS Account IDs are intentionally hard-coded as a safety measure.
     *
     * This prevents accidental deployments to the wrong AWS account
     * when switching between multiple AWS CLI profiles.
     * If the profile's account doesn't match this value,
     * CloudFormation will fail before making any changes.
     */
    readonly env: {
        readonly account: string;
        readonly region: string;
    };
    readonly stacks: {
        readonly platform: {
            readonly cpu: number;
            readonly memoryLimitMiB: number;
            readonly desiredCount: number;
            readonly useSpot: boolean;
            readonly domainName: string;
        };
        readonly queue: object;
        readonly rds: {
            readonly databaseName: string;
            readonly instanceClass: InstanceClass;
            readonly instanceIdentifier: string;
            readonly instanceSize: InstanceSize;
        };
    };
};

export const CONFIG_PER_STAGE: Record<StageName, ConfigPerStage> = {
    production: {
        env: {
            account: "277328279708",
            region: "eu-central-1", // Frankfurt
        },
        stacks: {
            platform: {
                cpu: 256,
                memoryLimitMiB: 512,
                desiredCount: 1,
                useSpot: false,
                domainName: "shirtsupplier.app",
            },
            queue: {},
            rds: {
                databaseName: "shirt_supplier",
                instanceClass: InstanceClass.BURSTABLE3,
                instanceIdentifier: "production-db",
                instanceSize: InstanceSize.MICRO,
            },
        },
    },
};
