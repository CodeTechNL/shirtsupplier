const isAllowedValue = <T extends string>(value: string, allowedValues: readonly T[]): value is T => {
    return allowedValues.some((item) => item === value);
};

export const Env = {
    getStringOrDefault(variableName: string, defaultValue: string): string {
        const value = process.env[variableName];

        if (value === undefined || value === "") {
            return defaultValue;
        }

        return value;
    },

    getStringOrThrow(variableName: string): string {
        const value = process.env[variableName];

        if (value === undefined) {
            throw new Error(`Missing required environment variable: ${variableName}`);
        }

        if (value === "") {
            throw new Error(`Empty environment variable: ${variableName}`);
        }

        return value;
    },

    getBooleanOrThrow(variableName: string): boolean {
        const stringValue = this.getStringOrThrow(variableName);

        if (stringValue === "true") {
            return true;
        }

        if (stringValue === "false") {
            return false;
        }

        throw new Error(`Invalid boolean for ${variableName}: "${stringValue}" (expected "true" or "false")`);
    },

    getEnumOrThrow<T extends string>(variableName: string, allowedValues: readonly T[]): T {
        const value = this.getStringOrThrow(variableName);

        if (isAllowedValue(value, allowedValues)) {
            return value;
        }

        throw new Error(`Invalid value for ${variableName}: "${value}". Allowed values are: ${allowedValues.join(", ")}`);
    },

    getJsonOrThrow<T = unknown>(variableName: string): T {
        const stringValue = this.getStringOrThrow(variableName);

        try {
            return JSON.parse(stringValue) as T;
        } catch {
            throw new Error(`Invalid JSON for ${variableName}`);
        }
    },

    getNumberOrThrow(variableName: string): number {
        const stringValue = this.getStringOrThrow(variableName);
        const parsedNumber = Number(stringValue);

        if (Number.isFinite(parsedNumber)) {
            return parsedNumber;
        }

        throw new Error(`Invalid number for ${variableName}: "${stringValue}"`);
    },
};
