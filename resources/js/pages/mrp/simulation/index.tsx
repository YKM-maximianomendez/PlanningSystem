import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import TimeLine from './time-line';
import { useAppearance } from '@/hooks/use-appearance';
import { useState } from 'react';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';

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

export interface Product {
    level: number;
    productId: number;
    productCode: string;
    mdiId: number;
    mdiCode: string;
    workcenterId: number;
    workcenterCode: string;
    um: string;
    quantityRequired: number;
    isObsolete: boolean;
    lastCycleCount?: {
        date: string;
        quantity: number;
        consumed: number;
        theoricalQuantity: number;
        diffDays: number;
    };
}

export interface MDI {
    mdiId: number;
    mdiCode: string;
}

export interface Material {
    materialId: number;
    materialCode: string;
    classId: number;
    classCode: string;
    classDescription: string;
    vendor: {
        vendorId: number;
        vendorCode: string;
        vendorDescription: string;
    };
    um: 'KG' | 'EA';
    isObsolete: boolean;
    options: {
        cutoff_start_date: string;
        cutoff_quantity: number;
    }
}

export type PlanningRow = {
    concept: string;
    values: Concept;
}

interface IndexProps {
    productionPlanningId: number;
    concepts: Record<string, Concept>;
    conceptsMap: ConceptStructure;
    planningRange: PlanningRange;
    configuration: {
        level: number;
        mdi: MDI;
        material: Material;
        planning: {
            product: Product;
            blankProduct?: Product;
        };
        products: Product[];
    };
    customer: {
        customerId: number;
        customerCode: string;
    }
    orders: {
        date: string;
        quantity: number;
        orderStatus: string;
        orderLocation: string;
    }[];
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

export type Order = {
    date: string;
    quantity: number;
};

export type ProductionPlan = {
    date: string;
    quantity: number;
    productId: number;
};

export default function Index({ productionPlanningId, concepts, conceptsMap, planningRange, configuration, orders }: IndexProps) {
    const { appearance } = useAppearance();
    const [refreshing, setRefreshing] = useState(false);
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Simulation',
            href: `#`,
        },
        {
            title: `${configuration.mdi.mdiCode}`,
            href: `/mrp/simulation/${productionPlanningId}`,
        }
    ];

    const { setData, data, post, processing, transform, reset, clearErrors } = useForm({
        orders: [] as Order[],
        productionPlan: [] as ProductionPlan[],
    });

    const handleRefresh = () => {
        router.reload({
            only: ['concepts', 'conceptsMap', 'planningRange', 'configuration'],
            onStart: () => {
                setRefreshing(true);
            },
            onFinish: () => {
                setRefreshing(false);
            },
            onError: () => {
                console.log('An error occurred');
            }
        });
    };

    const planningProducts = configuration.products.filter(product => product.level === configuration.level);

    const handleSaveSimulation = () => {
        transform((data) => ({
            ...data,
            productionPlanningId: productionPlanningId,
            orders: data.orders?.map((order) => ({
                ...order,
                vendorCode: configuration.material.vendor.vendorCode,
                productCode: configuration.material.materialCode,
                workcenterCode: '111010',
            })),
            productionPlan: data.productionPlan?.map((plan) => ({
                ...plan,
                workcenterId: configuration.planning.product.workcenterId,
                um: configuration.planning.product.um,
            })),
        }));

        post(route('mrp.simulation.store', productionPlanningId), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => console.log('Guardado con éxito'),
            onError: (errors) => console.log('Errores:', errors),
        });
    }

    const handleOrderChange = (order: Order) => {
        const currentOrders = data.orders || [];

        // if (order.quantity === 0) {
        //     setData(
        //         'orders',
        //         currentOrders.filter((item) => item.date !== order.date)
        //     );
        //     return;
        // }

        const exists = currentOrders.some(
            (item) => item.date === order.date
        );

        if (exists) {
            setData(
                'orders',
                currentOrders.map((item) =>
                    item.date === order.date
                        ? { ...item, quantity: order.quantity }
                        : item
                )
            );
        } else {
            setData('orders', [...currentOrders, order]);
        }
    };

    const handleProductionPlan = (productionPlan: ProductionPlan) => {
        const currentOrders = data.productionPlan || [];

        const updatedOrders = currentOrders.map((item) => {
            if (item.date === productionPlan.date) {
                return {
                    ...item,
                    quantity: productionPlan.quantity,
                };
            }

            return item;
        });

        const exists = currentOrders.some(
            (item) =>
                item.date === productionPlan.date &&
                item.productId === productionPlan.productId
        );

        if (!exists) {
            updatedOrders.push(productionPlan);
        }

        setData('productionPlan', updatedOrders);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Simulation" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-2">
                    <HeadingSmall title="Simulation" description="Manage your simulation and production scheduling." />
                    <div className="flex items-center gap-2">
                        <span>{configuration.planning.product.productCode ?? ''}</span>
                        <Button
                            onClick={handleRefresh}
                            disabled={refreshing || processing}
                            variant={'outline'}
                            size={'sm'}
                        >
                            Refresh
                        </Button>
                        <Button
                            onClick={handleSaveSimulation}
                            disabled={refreshing || processing}
                            variant={'secondary'}
                            size={'sm'}
                        >
                            Save Simulation
                        </Button>
                    </div>
                </div>
                <TimeLine
                    productionPlanningId={productionPlanningId}
                    concepts={concepts}
                    conceptsMap={conceptsMap}
                    planningRange={planningRange}
                    appearance={appearance}
                    orderChange={handleOrderChange}
                    productionPlanChange={handleProductionPlan}
                    loading={refreshing || processing}
                    planning={{
                        products: planningProducts,
                        blankProduct: configuration.planning?.blankProduct,
                        product: configuration.planning.product,
                    }}
                    orders={orders}
                    level={configuration.level}
                />
            </div>
        </AppLayout>
    );
}
