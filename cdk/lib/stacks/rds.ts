import {CfnOutput, RemovalPolicy, SecretValue} from "aws-cdk-lib";
import {StringParameter} from "aws-cdk-lib/aws-ssm";
import {InstanceClass, InstanceSize, InstanceType, SecurityGroup, SubnetType} from "aws-cdk-lib/aws-ec2";
import {
    Credentials,
    DatabaseInstance,
    DatabaseInstanceEngine,
    MysqlEngineVersion,
    StorageType
} from "aws-cdk-lib/aws-rds";
import type {Construct} from "constructs";
import {RegionalStack, RegionalStackProps} from "../RegionalStack";

interface RdsStackProps extends RegionalStackProps {
  databaseName: string;
  instanceClass: InstanceClass;
  instanceIdentifier: string;
  instanceSize: InstanceSize;
  readonly rdsUsername:string;
  readonly rdsPassword:string;
}

export class RdsStack extends RegionalStack {
  readonly databaseSecurityGroup: SecurityGroup;
  readonly dbInstanceEndpointAddress: string;
  readonly instanceIdentifier: string;

  constructor(scope: Construct, id: string, props: RdsStackProps) {
    super(scope, id, props);

    const { databaseName, instanceClass, instanceIdentifier, instanceSize, rdsPassword, rdsUsername } = props;

    this.instanceIdentifier = instanceIdentifier;

    const vpc = this.getDefaultVpc();


    const securityGroup = new SecurityGroup(this, "SecurityGroup", {
      allowAllOutbound: false,
      description: "SecurityGroup associated with the MySQL RDS Instance",
      vpc,
    });

    const db = new DatabaseInstance(this, "DatabaseInstance", {
      databaseName,
      instanceIdentifier,
      allocatedStorage: 20,
      autoMinorVersionUpgrade: true,
      allowMajorVersionUpgrade: false,
      engine: DatabaseInstanceEngine.mysql({
        version: MysqlEngineVersion.VER_8_4_5,
      }),
      publiclyAccessible: true,
      instanceType: InstanceType.of(instanceClass, instanceSize),
      maxAllocatedStorage: 50,
      vpcSubnets: { subnetType: SubnetType.PUBLIC },
      multiAz: false,
      securityGroups: [securityGroup],
      vpc,
      credentials: Credentials.fromPassword(rdsUsername, SecretValue.unsafePlainText(rdsPassword)),
        storageType: StorageType.GP3
    });

    this.databaseSecurityGroup = securityGroup;
    this.dbInstanceEndpointAddress = db.dbInstanceEndpointAddress;

    // Write DB_HOST to SSM so PlatformStack can read it without cyclic dependency
    new StringParameter(this, "DbHostParameter", {
      parameterName: `/${this.stageName}/DB_HOST`,
      stringValue: db.dbInstanceEndpointAddress,
    });

    new CfnOutput(this, "CfnOutput", { value: db.instanceEndpoint.hostname });
  }
}
