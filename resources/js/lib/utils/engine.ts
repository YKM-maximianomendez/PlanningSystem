import { Concept, PlanningRange, PlanningRow, Product } from "@/pages/mrp/simulation";

interface RollForwardInventoryParams {
    days: string[];
    initial: number;
    demand: Record<string, number>;
    productionPlan: Record<string, number>;
    realProduction: Record<string, number>;
    today: string;
    inventoryAdjustments?: Record<string, number>;
}

export class Engine {
    static readonly INPUT_CONCEPTS = new Set([
        'CALENDAR',
        'DEMAND',
        'PRODUCTION_PLAN',
        'ACTUAL_PRODUCTION',
        'INVENTORY',
        'INVENTORY_ADJUSTMENTS',
        'ADB',
        'PLANNED_SHEETS',
        'CONFIRMED_SHEETS',
        'BLANK_PLANNED_STOCK_PIECES',
        'BLANK_PRODUCTION_PLAN',
        'BLANK_ACTUAL_PRODUCTION',
        'CONFIRMED_STEEL',
        'WEIGHT_FACTOR',
    ]);

    static readonly OUTPUT_CONCEPTS = new Set([
        'INVENTORY',
        'STOCK_DAYS',
        'SHEETS_INVENTORY',
        'BLANK_INVENTORY',
        'BLANK_PLANNED_PIECES',
        'BLANK_PLANNED_STOCK_PIECES',
    ]);

    execute(
        input: PlanningRow[],
        product: Product,
        planningRange: PlanningRange,
        blankProduct?: Product | null,
    ): Map<string, Concept> {
        const inputMap = this.buildInputMap(input);

        // Importante:
        // !== con OR hacía que esto siempre fuera true.
        const containsBlank =
            blankProduct !== null &&
            blankProduct !== undefined;

        const days = Object.keys(
            inputMap.get('CALENDAR') ?? {},
        ).sort();

        const today = planningRange.today;

        const demand = inputMap.get('DEMAND') ?? {};
        const productionPlan = inputMap.get('PRODUCTION_PLAN') ?? {};
        const actualProduction = inputMap.get('ACTUAL_PRODUCTION') ?? {};

        const inventory = this.rollForwardInventory({
            days,
            initial:
                (inputMap.get('INVENTORY') ?? {})[
                planningRange.start
                ] ?? 0,
            demand,
            productionPlan,
            realProduction: actualProduction,
            today,
            inventoryAdjustments:
                inputMap.get('INVENTORY_ADJUSTMENTS') ?? {},
        });

        const stockDays = this.calculateDailyStockDays(
            inventory,
            inputMap.get('ADB') ?? {},
        );

        const materialSource = this.getMaterialSource(
            inputMap,
            containsBlank,
        );

        const materialInventory = this.rollForwardMaterialInventory(
            days,
            containsBlank
                ? blankProduct?.lastCycleCount?.theoricalQuantity ?? 0
                : (inputMap.get('SHEETS_INVENTORY') ?? {})[
                planningRange.start
                ] ?? 0,
            materialSource,
            productionPlan,
            actualProduction,
            today,
        );

        const weightFactor =
            (inputMap.get('WEIGHT_FACTOR') ?? {})[today] ?? 1.0;

        const plannedPieces = this.forwardPlannedPieces(
            inputMap.get(
                containsBlank
                    ? 'CONFIRMED_STEEL'
                    : 'CONFIRMED_SHEETS',
            ) ?? {},
            weightFactor,
        );

        const plannedStockPieces = this.forwardPlannedStock(
            (
                inputMap.get(
                    containsBlank
                        ? 'BLANK_PLANNED_STOCK_PIECES'
                        : 'SHEETS_INVENTORY',
                ) ?? {}
            )[planningRange.start] ?? 0,
            plannedPieces,
            materialSource,
        );

        this.applyMaterialConcepts(
            inputMap,
            containsBlank,
            materialInventory,
            plannedPieces,
            plannedStockPieces,
        );

        return this.buildOutput(
            inputMap,
            inventory,
            stockDays,
        );
    }

    private buildInputMap(input: PlanningRow[]): Map<string, Concept> {
        const inputMap = new Map<string, Concept>();

        for (const row of input) {
            inputMap.set(row.concept, row.values);
        }

        return inputMap;
    }

    private rollForwardInventory({
        days,
        initial,
        demand,
        productionPlan,
        realProduction,
        today,
        inventoryAdjustments = {},
    }: RollForwardInventoryParams): Record<string, number> {
        let balance = initial;

        const result: Record<string, number> = {};
        const todayInt = Number(today);

        for (const day of days) {
            if (day in inventoryAdjustments) {
                balance = inventoryAdjustments[day];
            }

            const production =
                Number(day) < todayInt
                    ? (realProduction[day] ?? 0)
                    : (productionPlan[day] ?? 0);

            balance += production;
            balance -= demand[day] ?? 0;

            result[day] = balance;
        }

        return result;
    }

    private calculateDailyStockDays(
        inventory: Record<string, number>,
        adb: Record<string, number>,
    ): Record<string, number> {
        const result: Record<string, number> = {};
        let lastValidValue = 0;

        for (const [date, balance] of Object.entries(inventory)) {
            const adbValue = adb[date] ?? 0;

            if (adbValue === 0) {
                result[date] = lastValidValue;
                continue;
            }

            const calculatedValue = Number(
                (balance / adbValue).toFixed(2),
            );

            result[date] = calculatedValue;
            lastValidValue = calculatedValue;
        }

        return result;
    }

    private getMaterialSource(
        concepts: Map<string, Concept>,
        containsBlank: boolean,
    ): Record<string, number> {
        if (containsBlank) {
            return {
                ...(concepts.get('BLANK_ACTUAL_PRODUCTION') ?? {}),
                ...(concepts.get('BLANK_PRODUCTION_PLAN') ?? {}),
            };
        }

        return {
            ...(concepts.get('PLANNED_SHEETS') ?? {}),
            ...(concepts.get('CONFIRMED_SHEETS') ?? {}),
        };
    }

    private rollForwardMaterialInventory(
        days: string[],
        initial: number,
        source: Record<string, number>,
        plannedProduction: Record<string, number>,
        actualProduction: Record<string, number>,
        today: string,
    ): Record<string, number> {
        let balance = initial;

        const result: Record<string, number> = {};
        const todayInt = Number(today);

        for (const day of days) {
            const production =
                Number(day) < todayInt
                    ? (actualProduction[day] ?? 0)
                    : (plannedProduction[day] ?? 0);

            balance += source[day] ?? 0;
            balance -= production;

            result[day] = balance;
        }

        return result;
    }

    private forwardPlannedPieces(
        entries: Record<string, number>,
        weightFactor: number,
    ): Record<string, number> {
        const result: Record<string, number> = {};

        for (const [date, value] of Object.entries(entries)) {
            const calculatedValue =
                weightFactor !== 0
                    ? value / weightFactor
                    : value;

            result[date] = Math.floor(calculatedValue);
        }

        return result;
    }

    private forwardPlannedStock(
        initialValue: number,
        plannedPieces: Record<string, number>,
        materialSource: Record<string, number>,
    ): Record<string, number> {
        let balance = initialValue;

        const result: Record<string, number> = {};

        for (const [date, pieces] of Object.entries(plannedPieces)) {
            balance += pieces;
            balance -= materialSource[date] ?? 0;

            result[date] = balance;
        }

        return result;
    }

    private applyMaterialConcepts(
        inputMap: Map<string, Concept>,
        containsBlank: boolean,
        materialInventory: Record<string, number>,
        plannedPieces: Record<string, number>,
        plannedStockPieces: Record<string, number>,
    ): void {
        if (containsBlank) {
            inputMap.set('BLANK_INVENTORY', materialInventory);
            inputMap.set('BLANK_PLANNED_PIECES', plannedPieces);
            inputMap.set(
                'BLANK_PLANNED_STOCK_PIECES',
                plannedStockPieces,
            );

            return;
        }

        inputMap.set('SHEETS_INVENTORY', materialInventory);
    }

    private buildOutput(
        inputMap: Map<string, Concept>,
        inventory: Record<string, number>,
        stockDays: Record<string, number>,
    ): Map<string, Concept> {
        return new Map([
            ['INVENTORY', inventory],
            ['STOCK_DAYS', stockDays],
            [
                'SHEETS_INVENTORY',
                inputMap.get('SHEETS_INVENTORY') ?? {},
            ],
            [
                'BLANK_INVENTORY',
                inputMap.get('BLANK_INVENTORY') ?? {},
            ],
            [
                'BLANK_PLANNED_STOCK_PIECES',
                inputMap.get(
                    'BLANK_PLANNED_STOCK_PIECES',
                ) ?? {},
            ],
        ]);
    }
}
