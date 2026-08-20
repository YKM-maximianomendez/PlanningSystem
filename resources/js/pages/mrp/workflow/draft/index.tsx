import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import DataTable from './data-table';
import { useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

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
    location: string;
}

export default function Index({ workcenterCode, orders, location }: IndexProps) {
    const [value, setValue] = useState(location || 'YH0160');
    const [isLoading, setIsLoading] = useState(false);

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

    const handleValueChange = (newValue: string) => {
        setValue(newValue);
        router.reload({
            data: { location: newValue },
            onStart: () => setIsLoading(true),
            onFinish: () => setIsLoading(false),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Draft Workflow" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-2">
                    <HeadingSmall title="Draft" description="Manage your draft workflow." />
                    <div className="flex items-center gap-2">
                        <ToggleGroup
                            value={value}
                            onValueChange={handleValueChange}
                            variant="outline"
                            type="single"
                            size="sm"
                        >
                            <ToggleGroupItem value="YH0160" aria-label="Toggle draft" disabled={isLoading}>
                                Draft
                            </ToggleGroupItem>

                            <ToggleGroupItem value="YH0161" aria-label="Toggle processed" disabled={isLoading}>
                                Processed
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                </div>
                <DataTable orders={orders} isLoading={isLoading} />
            </div>
        </AppLayout >
    );
}
