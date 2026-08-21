import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Delivery Instruction',
        href: '/mrp/delivery-instruction',
    },
];

export default function Index() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Delivery Instruction" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className='flex items-center justify-between gap-2'>
                    <HeadingSmall title="Delivery Instruction" description="Manage your delivery instructions." />
                    <div>
                        <Button variant="destructive" size="sm">Run Program</Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
