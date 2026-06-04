#!/usr/bin/env node
import * as cdk from 'aws-cdk-lib/core';
import {QueueStack} from "../lib/stacks/queue";
import {RdsStack} from "../lib/stacks/rds";
import {PlatformStack} from "../lib/stacks/platform";
import {Env} from "../lib/env";
import {StageName} from "../lib/stages";
import {CONFIG_PER_STAGE} from "../lib/config";

const app = new cdk.App();

const stageName = Env.getStringOrThrow('STAGE') as StageName;
const {stacks, env} = CONFIG_PER_STAGE[stageName]
const stackId = (stageName: StageName, type: string) => `${stageName}-${type}`

const queueStack = new QueueStack(app, stackId(stageName, 'Queue'), {
    env,
    stageName
})

const rdsStack = new RdsStack(app, stackId(stageName, 'Rds'), {
    env,
    stageName,
    databaseName: stacks.rds.databaseName,
    instanceClass: stacks.rds.instanceClass,
    instanceIdentifier: stacks.rds.instanceIdentifier,
    instanceSize: stacks.rds.instanceSize,
    rdsPassword: Env.getStringOrThrow('DB_PASSWORD'),
    rdsUsername: Env.getStringOrThrow('DB_USERNAME')
})

new PlatformStack(app, stackId(stageName, 'Platform'), {
    env,
    stageName,
    databaseSecurityGroup: rdsStack.databaseSecurityGroup,
    queue: queueStack.queue,
    ...stacks.platform,
})

app.synth();
