import { Concept } from "@/pages/mrp/simulation";
import { parse, format, subDays, isWeekend } from "date-fns";

export interface ValidationResult {
    valid: boolean;
    message?: string;
    affectedCells?: {
        concept: string;
        date: string;
    }[]
}

export const validateConceptChange = (
    concepts: Record<string, Concept>,
    concept: string,
    date: string,
    value: number,
    orders: {
        date: string;
        quantity: number;
        orderStatus: string;
        orderLocation: string;
    }[] = []
): ValidationResult => {
    const parsedDate = parse(date, 'yyyyMMdd', new Date());
    const today = format(new Date(), 'yyyyMMdd');
    const formatDateString = format(parsedDate, 'dd/MM/yyyy');
    const previousDate = format(
        subDays(parse(date, 'yyyyMMdd', new Date()), 1),
        'yyyyMMdd'
    );

    if (value < 0) {
        return {
            valid: false,
            message: `Planeación no permitida: ${formatDateString} en ${concept} no puede ser menor a 0.`,
            affectedCells: [
                {
                    concept,
                    date
                }
            ]
        };
    }

    if (parsedDate < parse(today, 'yyyyMMdd', new Date())) {
        return {
            valid: false,
            message: `Planeación no permitida: ${formatDateString} es una fecha pasada.`,
        };
    }

    if (isWeekend(parsedDate)) {
        return {
            valid: false,
            message: `Planeación no permitida: ${formatDateString} corresponde a un fin de semana.`,
        };
    }

    if (concept === 'CONFIRMED_SHEETS') {
        const plannedSheets = concepts.PLANNED_SHEETS?.[date] ?? 0;

        if (plannedSheets <= 0) {
            return {
                valid: false,
                message: `Confirmación no permitida: no existen hojas planeadas para el ${formatDateString}.`,
            };
        }
    }

    if (concept === 'CONFIRMED_STEEL') {
        const plannedSteel = concepts.PLANNED_STEEL?.[date] ?? 0;

        if (plannedSteel <= 0) {
            return {
                valid: false,
                message: `Confirmación no permitida: no existe acero planeado para el ${formatDateString}.`,
                affectedCells: [
                    {
                        concept: concepts.CONFIRMED_STEEL ? 'PLANNED_STEEL' : 'PLANNED_SHEETS',
                        date: date
                    }
                ]
            };
        }
    }

    if (concept === 'PRODUCTION_PLAN') {
        const inventoryConcept = concepts.SHEETS_INVENTORY ? concepts.SHEETS_INVENTORY : concepts.BLANK_INVENTORY;
        const blankInventory =
            inventoryConcept?.[previousDate] ?? 0;

        if (value > blankInventory) {
            return {
                valid: false,
                message: `Planeación no permitida: ${value} piezas excede el inventario disponible de blanks/sheets (${blankInventory} piezas) al ${previousDate}.`,
                affectedCells: [
                    {
                        concept: concepts.SHEETS_INVENTORY ? 'SHEETS_INVENTORY' : 'BLANK_INVENTORY',
                        date: previousDate,
                    },
                ],
            };
        }
    }

    if (concept == 'BLANK_PRODUCTION_PLAN') {
        const plannedStockPieces = concepts.BLANK_PLANNED_STOCK_PIECES;
        const plannedStock = plannedStockPieces?.[previousDate] ?? 0;

        if (value > plannedStock) {
            return {
                valid: false,
                message: `Planeación no permitida: ${value} piezas excede el stock planeado de blanks (${plannedStock} piezas) al ${previousDate}.`,
                affectedCells: [
                    {
                        concept: 'BLANK_PLANNED_STOCK_PIECES',
                        date: previousDate,
                    },
                ],
            }
        }
    }

    if (concept === 'PLANNED_STEEL' || concept === 'PLANNED_SHEETS') {
        const searchOrder = orders.find(f => f.date === date);
        if (searchOrder) {
            if (searchOrder.orderLocation === 'HPO') {
                return {
                    valid: false,
                    message: `Ya existe una orden en HPO para esta fecha (${formatDateString}). No se permite modificar la planeación de acero/hojas.`,
                    affectedCells: [
                        {
                            concept: concept,
                            date: date,
                        },
                    ],
                };
            }
        }
    }

    return {
        valid: true,
    };
};