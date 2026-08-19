import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import DataTable from './data-table';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { useState } from 'react';
import { FolderSync } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export type ProductionPlanning = {
    materialId: number;
    materialCode: string;
    classId: number;
    classCode: string;
    classDescription: string;
    MDIId: number;
    MDICode: string;
    level: number;
    canBePlanned: boolean;
    customerId: number;
    customerCode: string;
    customerDescription: string;
    productionPlanningId: number | null;
    stockDays: number | null;
}

interface IndexProps {
    productionPlannings: ProductionPlanning[];
}

export default function Index({ productionPlannings }: IndexProps) {
    const [isLoading, setIsLoading] = useState(false);
    const handleRefresh = () => {
        setIsLoading(true);
        router.reload({
            only: ['productionPlannings'],
            onStart: () => {
                setIsLoading(true);
            },
            onFinish: () => {
                setIsLoading(false);
            },
            onError: () => {
                console.log('An error occurred');
            }
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workcenter Planning" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-2">
                    <HeadingSmall title="Workcenter Planning" description="Manage your workcenter planning and production scheduling." icon="WorkflowIcon" />
                    <div className="flex items-center gap-2">
                        <Button
                            onClick={handleRefresh}
                            disabled={isLoading}
                            variant={'outline'}
                            size={'sm'}
                        >
                            <svg className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </Button>
                        <Button
                            variant={'outline'}
                            size={'sm'}
                            disabled={true}
                        >
                            <FolderSync className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                <DataTable productionPlannings={productionPlannings} isLoading={isLoading} />
            </div>
        </AppLayout>
    );
}
