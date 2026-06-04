import {CfnOutput} from "aws-cdk-lib";
import {Queue} from "aws-cdk-lib/aws-sqs";
import type {Construct} from "constructs";
import { RegionalStack, RegionalStackProps } from "../RegionalStack";

interface QueueStackProps extends RegionalStackProps {
    //
}

export class QueueStack extends RegionalStack {
    readonly queue: Queue;

    constructor(scope: Construct, id: string, props: QueueStackProps) {
        super(scope, id, props);

        const jobQueue = new Queue(this, "Queue", {});

        new CfnOutput(this, "CfnOutput", {
            value: jobQueue.queueArn,
            description: "SQS Queue ARN",
        });

        this.queue = jobQueue;
    }
}
