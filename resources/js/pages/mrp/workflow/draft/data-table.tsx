import { useAgGridTheme } from "@/hooks/use-ag-grid-theme";
import { AllCommunityModule, ColDef } from "ag-grid-community";
import { AgGridProvider, AgGridReact } from "ag-grid-react";
import { useMemo } from "react";
import type { Order, OrderStatus } from "./index";
import { AlertTriangle, CheckCircle2, CircleHelp, Minus, Pencil } from "lucide-react";

interface DataTableProps {
    orders: Order[];
    isLoading?: boolean;
}

const statusConfig: Record<
    Exclude<OrderStatus, null>,
    {
        label: string;
        icon: typeof Pencil;
        className: string;
    }
> = {
    ED: {
        label: "Editing",
        icon: Pencil,
        className: "text-blue-400",
    },
    PE: {
        label: "Posted with errors",
        icon: AlertTriangle,
        className: "text-amber-400",
    },
    PO: {
        label: "Posted",
        icon: CheckCircle2,
        className: "text-green-400",
    },
    UN: {
        label: "Unknown",
        icon: CircleHelp,
        className: "text-gray-400",
    },
};

export default function DataTable({ orders, isLoading = false }: DataTableProps) {
    const theme = useAgGridTheme();

    const columnDefs = useMemo<ColDef<Order>[]>(
        () => [
            {
                headerName: "Product Code",
                field: "productCode",
                pinned: "left",
                lockPinned: true,
            },
            {
                headerName: "Quantity Ordered",
                field: "quantityOrdered",
                width: 140,
                cellDataType: "number",
            },
            {
                headerName: "Due Date",
                field: "dueDate",
                width: 110,
                cellClass: "text-center",
            },
            {
                headerName: "Vendor Name",
                field: "vendorName",
                width: 250,
            },
            {
                headerName: "Status",
                field: "status",
                width: 100,
                cellClass: "flex items-center justify-center",
                cellRenderer: ({ value }: { value: OrderStatus }) => {
                    if (!value) {
                        return (
                            <Minus
                                size={14}
                                className="text-gray-500"
                                aria-label="No status"
                            />
                        );
                    }

                    const config = statusConfig[value];
                    const Icon = config.icon;

                    return (
                        <div
                            className={`flex items-center justify-center ${config.className}`}
                            title={config.label}
                        >
                            <Icon size={14} />
                        </div>
                    );
                },
            },
            {
                headerName: "Order Number",
                field: "order",
                width: 120,
            },
            {
                headerName: "Status Message",
                field: "text",
                width: 300,
            },
        ],
        [],
    );

    const defaultColDef = useMemo<ColDef<Order>>(
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
                <AgGridProvider modules={[AllCommunityModule]}>
                    <AgGridReact<Order>
                        rowData={orders}
                        columnDefs={columnDefs}
                        defaultColDef={defaultColDef}
                        suppressCellFocus={true}
                        pagination={true}
                        theme={theme}
                        asyncTransactionWaitMillis={1000}
                        animateRows={true}
                        suppressRowHoverHighlight={false}
                        loading={isLoading}
                        suppressHorizontalScroll
                    />
                </AgGridProvider>
            </div>
        </>
    );
}