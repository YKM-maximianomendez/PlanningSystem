import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import DataTable from './data-table';

export type OrderStatus = "ED" | "PE" | "PO" | "UN" | null;

export interface Order {
    productCode: string;
    quantityOrdered: number;
    dueDate: string;
    vendorCode: string;
    vendorName: string;
    status: OrderStatus;
    location: string;
    text: string | null;
    orderNumber: string | null;
    lineNumber: string | null;
    order: string | null;
}

interface IndexProps {
    workcenterCode: string;
    orders: Order[];
}

export default function Index({ workcenterCode, orders }: IndexProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Workflow',
            href: '/mrp/workflow',
        },
        {
            title: 'Draft',
            href: '/mrp/workflow/draft',
        },
        {
            title: workcenterCode,
            href: `/mrp/workflow/draft/${workcenterCode}`,
        }
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Draft Workflow" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-2">
                    <HeadingSmall title="Draft" description="Manage your draft workflow." />
                    <div className="flex items-center gap-2">

                    </div>
                </div>
                <DataTable orders={orders} isLoading={false} />
            </div>
        </AppLayout >
    );
}
