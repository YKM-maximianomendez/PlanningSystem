import { customFormatDate, Order, Product, ProductionPlan, type Concept, type ConceptStructure, type PlanningRange, type PlanningRow } from './index';
import { AgGridProvider, AgGridReact } from 'ag-grid-react';
import {
    AllCommunityModule,
    CellStyle,
    CellValueChangedEvent,
    ColDef,
    GridApi,
    ICellRendererParams,
    IRowNode,
    TextEditorModule,
    ValueFormatterParams,
    ValueGetterParams,
} from 'ag-grid-community';
import { useMemo, useRef } from 'react';
import { addDays, format, isWeekend, parse } from 'date-fns';
import { useAgGridTheme } from '@/hooks/use-ag-grid-theme';
import { validateConceptChange } from '@/utils/validateConceptChange';
import { toast } from 'sonner';

interface TimeLineProps {
    productionPlanningId: number;
    concepts: Record<string, Concept>;
    conceptsMap: ConceptStructure;
    planningRange: PlanningRange;
    appearance: 'light' | 'dark' | 'system';
    orderChange: (order: Order) => void;
    productionPlanChange: (productionPlan: ProductionPlan) => void;
    loading?: boolean;
    planning: {
        product: Product;
        blankProduct?: Product;
    };
    orders?: {
        date: string;
        quantity: number;
        orderStatus: string;
        orderLocation: string;
    }[] | [];
}

export default function TimeLine({
    productionPlanningId,
    concepts,
    conceptsMap,
    planningRange,
    appearance,
    orderChange,
    productionPlanChange,
    loading = false,
    planning,
    orders = [],
}: TimeLineProps) {
    const gridApiRef = useRef<GridApi | null>(null);

    const EDITABLE_CONCEPTS = new Set([
        'PRODUCTION_PLAN',
        'PLANNED_STEEL',
        'CONFIRMED_STEEL',
        'BLANK_PRODUCTION_PLAN',
        'PLANNED_SHEETS',
        'CONFIRMED_SHEETS',
    ]);

    /**
     * Calculate the timeline dates based on the planning range. This will create an array of date strings in 'yyyyMMdd' format, starting from the planningRange.start date and ending at the planningRange.end date. The total number of days is determined by the difference between endJulianDay and startJulianDay, inclusive.
     * The useMemo hook is used to memoize the result, so it only recalculates when the planningRange.start, planningRange.startJulianDay, or planningRange.endJulianDay values change.
     * This ensures that the timeline dates are efficiently computed and updated only when necessary.
     */
    const timelineDates = useMemo<string[]>(() => {
        const startDate = parse(planningRange.start, 'yyyyMMdd', new Date());
        const totalDays = planningRange.endJulianDay - planningRange.startJulianDay + 1;

        return Array.from({ length: totalDays }, (_, i) =>
            format(addDays(startDate, i), 'yyyyMMdd')
        );
    }, [planningRange.start, planningRange.startJulianDay, planningRange.endJulianDay]);

    /**
     * Generate the row data for the AgGrid table based on the concepts and conceptsMap. Each row represents a concept and its associated values. The rows are sorted based on the order defined in the conceptsMap, ensuring that they are displayed in a meaningful sequence.
     * The useMemo hook is used to memoize the result, so it only recalculates when the concepts or conceptsMap values change. This optimizes performance by preventing unnecessary recalculations and re-renders of the table rows.
     * The resulting rowData is an array of PlanningRow objects, each containing a concept and its corresponding values.
     */
    const rowData = useMemo<PlanningRow[]>(() => {
        return Object.entries(concepts)
            .map(([concept, values]) => ({ concept, values }))
            .sort((a, b) => {
                const orderA = conceptsMap[a.concept]?.order ?? 999;
                const orderB = conceptsMap[b.concept]?.order ?? 999;
                return orderA - orderB;
            });
    }, [concepts, conceptsMap]);

    const columnDefs = useMemo<ColDef<PlanningRow>[]>(() => {
        const conceptColumn: ColDef<PlanningRow> = {
            field: 'concept',
            headerName: 'Concepto',
            pinned: 'left',
            lockPinned: true,
            suppressMovable: true,
            width: 200,
            minWidth: 200,
            maxWidth: 200,
            sortable: false,
            cellClass: 'font-bold bg-red-50 dark:bg-red-950',
            valueFormatter: (params) =>
                conceptsMap[params.value]?.description ?? params.value,
            cellStyle: (params) => {
                const concept = params.data?.concept ?? '';

                // if (concept.startsWith('BLANK') || concept.toUpperCase().includes('SHEETS')) {
                //     return {
                //         backgroundColor: appearance === 'dark' ? '#1E3A2A' : '#E8F5E9',
                //         color: appearance === 'dark' ? '#A5D6A7' : '#2E7D32',
                //         fontWeight: '500',
                //     };
                // }

                // if (concept === 'STOCK_DAYS') {
                //     return {
                //         backgroundColor:
                //             appearance === 'dark' ? '#1E3552' : '#E3F2FD',
                //         color:
                //             appearance === 'dark' ? '#90CAF9' : '#1565C0',
                //         fontWeight: '500',
                //     };
                // }

                return undefined;
            },
        };

        const unitColumn: ColDef<PlanningRow> = {
            colId: 'unit',
            headerName: 'UM',
            pinned: 'left',
            lockPinned: true,
            suppressMovable: true,
            width: 60,
            minWidth: 60,
            maxWidth: 60,
            sortable: false,
            cellClass: 'text-center font-bold',
            valueGetter: (params: ValueGetterParams<PlanningRow>) =>
                conceptsMap[params.data?.concept ?? '']?.unit ?? '',
            cellStyle: (params) => {
                const concept = params.data?.concept ?? '';

                // if (concept.startsWith('BLANK') || concept.toUpperCase().includes('SHEETS')) {
                //     return {
                //         backgroundColor: appearance === 'dark' ? '#1E3A2A' : '#E8F5E9',
                //         color: appearance === 'dark' ? '#A5D6A7' : '#2E7D32',
                //         fontWeight: '500',
                //     };
                // }

                // if (concept === 'STOCK_DAYS') {
                //     return {
                //         backgroundColor:
                //             appearance === 'dark' ? '#1E3552' : '#E3F2FD',
                //         color:
                //             appearance === 'dark' ? '#90CAF9' : '#1565C0',
                //         fontWeight: '500',
                //     };
                // }

                return undefined;
            },
        };

        const dateColumns: ColDef<PlanningRow>[] =
            timelineDates.map((date) => {
                const weekend = isWeekend(parse(date, 'yyyyMMdd', new Date()));
                const isToday = date === planningRange.today;

                const headerClasses: string[] = [];
                if (weekend) headerClasses.push('ag-weekend-col');
                if (isToday) headerClasses.push('ag-today-col');

                return ({
                    colId: date,
                    headerName: customFormatDate(date),
                    headerClass: headerClasses.length ? headerClasses : undefined,
                    width: 95,
                    minWidth: 95,
                    maxWidth: 95,
                    sortable: false,
                    resizable: false,
                    suppressMovable: true,
                    editable: (params) => EDITABLE_CONCEPTS.has(params.data?.concept ?? ''),
                    cellStyle: (params): CellStyle | null | undefined => {
                        const editable = EDITABLE_CONCEPTS.has(params.data?.concept ?? '');
                        // if (weekend) {
                        //     return {
                        //         backgroundColor: appearance === 'dark' ? '#1c1c1c' : '#f0f0f0',
                        //         color: appearance === 'dark' ? '#555' : '#aaa',
                        //     };
                        // }

                        if (isToday) {
                            return {
                                backgroundColor: '#1e3552',
                            };
                        }

                        // if (isToday) {
                        //     return {
                        //         backgroundColor: appearance === 'dark' ? '#1e3552' : '#EFF6FF',
                        //     };
                        // }

                        // if (editable) {
                        //     return {
                        //         backgroundColor: appearance === 'dark' ? '#0d1f10' : '#F0FDF4',
                        //     };
                        // }
                        return undefined;
                    },
                    cellEditor: 'agNumberCellEditor',
                    valueGetter: (
                        params: ValueGetterParams<PlanningRow>,
                    ) => {
                        return (
                            params.data?.values?.[date] ??
                            null
                        );
                    },
                    valueSetter: (params) => {
                        const value = Number(params.newValue);
                        const concept = params.data?.concept;

                        if (!Number.isFinite(value) || !params.data) {
                            return false;
                        }

                        const result = validateConceptChange(
                            rowData.reduce((acc, row) => {
                                acc[row.concept] = row.values;
                                return acc;
                            }, {} as Record<string, Concept>),
                            concept ?? '',
                            date,
                            value,
                            orders
                        );

                        const resultAffectedCells = result.affectedCells ?? [];

                        if (resultAffectedCells.length > 0) {
                            resultAffectedCells.forEach(cell => {
                                const rowNode = params.api
                                    .getRenderedNodes()
                                    .find(
                                        node => node.data?.concept === cell.concept
                                    );

                                const column = params.api.getColumn(cell.date);

                                if (rowNode && column) {
                                    params.api.flashCells({
                                        rowNodes: [rowNode],
                                        columns: [column],
                                    });
                                }
                            });
                        }

                        if (result.valid === false) {
                            toast.error(result.message ?? 'Valor inválido');
                            return false;
                        }

                        params.data.values = {
                            ...params.data.values,
                            [date]: value,
                        };

                        return true;
                    },
                    valueFormatter: (
                        params: ValueFormatterParams,
                    ) => {
                        if (params.value == null) {
                            return '-';
                        }

                        return Number(params.value).toFixed(2);
                    },
                    valueParser: (params) => {
                        if (
                            params.newValue === null ||
                            params.newValue === undefined ||
                            params.newValue === ''
                        ) {
                            return null;
                        }

                        const value = Number(params.newValue);

                        return Number.isFinite(value)
                            ? value
                            : params.oldValue;
                    },
                    cellClass: (params) => {
                        const classes: string[] = [];
                        classes.push('text-center');

                        if (EDITABLE_CONCEPTS.has(params.data?.concept ?? '')) {
                            classes.push('cursor-text');
                        }

                        if (
                            params.data?.concept === 'STOCK_DAYS' &&
                            params.value != null &&
                            Number(params.value) < 1.5
                        ) {
                            classes.push('!text-red-500');
                        }

                        if (params.data?.concept === 'STOCK_DAYS') {
                            classes.push('font-bold');
                        }

                        if (Number(params.value) > 0) {
                            classes.push('font-bold');
                        }

                        return classes.join(' ');
                    },
                });
            });

        return [
            conceptColumn,
            unitColumn,
            ...dateColumns
        ];
    }, [timelineDates]);

    const handleCellValueChanged = (params: CellValueChangedEvent<PlanningRow>) => {
        if (!params.data) {
            return;
        }

        const concept = params.data.concept;
        const date = params.colDef?.colId ?? '';
        const value = Number(params.newValue);
        const oldValue = Number(params.oldValue);

        if (concept === 'PLANNED_STEEL' || concept === 'PLANNED_SHEETS') {
            orderChange({ date, quantity: value });
        }

        if (concept === 'PRODUCTION_PLAN' || concept === 'BLANK_PRODUCTION_PLAN') {
            if (concept === 'PRODUCTION_PLAN') {
                productionPlanChange({
                    date,
                    quantity: value,
                    productId: planning.product.productId
                });
            }

            if (concept === 'BLANK_PRODUCTION_PLAN') {
                productionPlanChange({
                    date,
                    quantity: value,
                    productId: planning.blankProduct?.productId ?? 0
                });
            }
        }
    }

    const handleSaveSimulation = () => {

    }

    const themeClass = useAgGridTheme();

    return (
        <div className="ag-theme-quartz w-full">
            <AgGridProvider modules={[AllCommunityModule, TextEditorModule]}>
                <AgGridReact<PlanningRow>
                    rowData={rowData}
                    headerHeight={25}
                    rowHeight={23}
                    columnDefs={columnDefs}
                    suppressHorizontalScroll
                    suppressMovableColumns
                    domLayout={'autoHeight'}
                    stopEditingWhenCellsLoseFocus
                    onCellValueChanged={handleCellValueChanged}
                    onGridReady={(e) => { gridApiRef.current = e.api; }}
                    theme={themeClass}
                    loading={loading}
                />
            </AgGridProvider>
        </div>
    );
}