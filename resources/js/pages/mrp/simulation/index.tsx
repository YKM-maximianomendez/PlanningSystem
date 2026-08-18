import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import TimeLine from './time-line';
import { useAppearance } from '@/hooks/use-appearance';

export type Concept = Record<string, number>;
export type ConceptStructure = Record<string, { description: string; unit: string; order: number }>;

export type PlanningRange = {
    end: string;
    start: string;
    today: string;
    startJulianDay: number;
    endJulianDay: number;
    window: {
        start: string;
        end: string;
    };
};

export type PlanningRow = {
    concept: string;
    values: Concept;
}

interface IndexProps {
    productionPlanningId: number;
    concepts: Record<string, Concept>;
    conceptsMap: ConceptStructure;
    planningRange: PlanningRange;
}

export const customFormatDate = (date: string) => {
    const year = Number(date.substring(0, 4));
    const month = Number(date.substring(4, 6)) - 1;
    const day = Number(date.substring(6, 8));

    return new Date(year, month, day).toLocaleDateString('es-MX', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
    });
};


export default function Index({ productionPlanningId, concepts, conceptsMap, planningRange }: IndexProps) {
    const { appearance } = useAppearance();
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Simulation',
            href: `/mrp/simulation/${productionPlanningId}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Simulation" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <TimeLine
                    productionPlanningId={productionPlanningId}
                    concepts={concepts}
                    conceptsMap={conceptsMap}
                    planningRange={planningRange}
                    appearance={appearance}
                />
            </div>
        </AppLayout>
    );
}
