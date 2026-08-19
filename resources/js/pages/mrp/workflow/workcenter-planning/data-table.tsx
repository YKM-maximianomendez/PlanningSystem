import { useAgGridTheme } from '@/hooks/use-ag-grid-theme';
import { cn } from '@/lib/utils';
import { AllCommunityModule, ColDef, ICellRendererParams } from 'ag-grid-community';
import { AgGridProvider, AgGridReact } from 'ag-grid-react';
import { PlayCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ProductionPlanning } from '.';
import OpenSimulation from './components/open-simulation';

const modules = [AllCommunityModule];

interface DataTableProps {
    productionPlannings: ProductionPlanning[];
    isLoading?: boolean;
}

const ActionCellRenderer = ({
    data,
    onSimulationClick,
}: ICellRendererParams<ProductionPlanning> & {
    onSimulationClick: (material: ProductionPlanning) => void;
}) => {
    const productionPlanningId = data?.productionPlanningId;

    if (!productionPlanningId) {
        return null;
    }

    const handleSimulationClick = () => {
        console.log('Resetear Troquel clicked for productionPlanningId:', productionPlanningId);
        onSimulationClick(data);
    };

    return (
        <div className="flex items-center justify-center gap-2 mt-1">
            <button
                onClick={handleSimulationClick}
                aria-label="Ver planificación del material"
                className={cn(
                    'text-blue-400 transition-colors',
                    'cursor-pointer hover:opacity-70 transition-opacity hover:scale-110',
                )}
                title='Open Simulation'
            >
                <PlayCircle
                    className="h-4 w-4"
                    aria-hidden="true"
                />
            </button>
        </div>
    );
};

export default function DataTable({ productionPlannings, isLoading = false }: DataTableProps) {
    const theme = useAgGridTheme();
    const [selectedMaterial, setSelectedMaterial] = useState<ProductionPlanning | null>(null);
    const [openSimulationDialog, setOpenSimulationDialog] = useState<boolean>(false);


    const columnDefs = useMemo<ColDef<ProductionPlanning>[]>(
        () => [
            {
                headerName: 'Material ID',
                field: 'materialId',
                width: 100,
                cellClass: 'text-center',
                valueFormatter: ({ value }) =>
                    value != null ? String(value).padStart(5, '0') : '',
            },
            {
                headerName: 'Material Code',
                field: 'materialCode',
                width: 150
            },
            {
                headerName: 'MDI Code',
                field: 'MDICode',
                width: 150,
                cellClass: 'font-bold',
            },
            {
                headerName: 'Level',
                field: 'level',
                width: 100,
                cellClass: 'text-center'
            },
            {
                headerName: 'Class Code',
                field: 'classCode',
                width: 100,
                cellClass: 'text-center',
            },
            {
                headerName: 'Customer Code',
                field: 'customerCode',
                width: 150,
                cellClass: 'text-center',
            },
            {
                headerName: 'Stock Days',
                field: 'stockDays',
                width: 100,
                cellClass: 'text-center',
            },
            {
                headerName: 'Actions',
                sortable: false,
                filter: false,
                resizable: false,
                cellRenderer: ActionCellRenderer,
                cellRendererParams: {
                    onSimulationClick: (material: ProductionPlanning) => {
                        setSelectedMaterial(material);
                        setOpenSimulationDialog(true);
                    },
                },
                width: 100,
                cellClass: 'text-center',
            }
        ],
        [],
    );

    const defaultColDef = useMemo<ColDef<ProductionPlanning>>(
        () => ({
            resizable: true,
            sortable: true,
            filter: true,
        }),
        [],
    );

    return (
        <>
            <div className="ag-theme-alpine h-full w-full">
                <AgGridProvider modules={modules}>
                    <AgGridReact<ProductionPlanning>
                        rowData={productionPlannings}
                        columnDefs={columnDefs}
                        defaultColDef={defaultColDef}
                        suppressCellFocus={true}
                        pagination={true}
                        theme={theme}
                        asyncTransactionWaitMillis={1000}
                        animateRows={true}
                        suppressRowHoverHighlight={false}
                        loading={isLoading}
                    />
                </AgGridProvider>
                <OpenSimulation
                    open={openSimulationDialog}
                    handleOpenChange={setOpenSimulationDialog}
                />
            </div>
        </>
    );
}
