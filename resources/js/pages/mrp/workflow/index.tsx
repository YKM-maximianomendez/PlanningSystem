import HeadingSmall from '@/components/heading-small';
import { Item, ItemActions, ItemContent, ItemTitle } from '@/components/ui/item';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Factory, FileText, WorkflowIcon } from 'lucide-react';
import { JSX } from 'react/jsx-runtime';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workflow',
        href: '/workflow',
    },
];

interface Workcenter {
    workcenter: string;
    workcenterCode: string;
    workcenterDescription: string;
    workcenterTypeName: string;
}

interface IndexProps {
    groupedWorkcenters: {
        title: string;
        workcenters: Workcenter[];
        priority: number;
    }[];
}

const WorkcenterItem = (props: Workcenter): JSX.Element => {
    return (
        <Item
            variant="outline"
            className="group border-2 border-green-500/60 transition-all duration-200 hover:border-green-500 hover:bg-green-50/30 dark:border-[#429356] dark:hover:border-[#68FF8E] dark:hover:bg-[#429356]/10"
        >
            <ItemContent className="flex gap-3">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-neutral-100 group-hover:bg-green-100 dark:bg-[#21222C] dark:group-hover:bg-[#429356]/20">
                    <Factory className="h-5 w-5 text-neutral-500 group-hover:text-green-600 dark:text-[#429356] dark:group-hover:text-[#68FF8E]" />
                </div>

                <div className="flex flex-col gap-0.5">
                    <ItemTitle className="text-sm leading-none font-semibold tracking-tight dark:text-[#50F178]" title={props.workcenterCode}>
                        {props.workcenterDescription}
                    </ItemTitle>

                    {/* <div className="mt-0.5 flex items-center gap-1">
                        <span className="text-[10px] text-neutral-400 italic dark:text-[#429356]">{props.workcenterCode}</span>
                    </div> */}
                </div>
            </ItemContent>
            <ItemActions className="flex gap-1">
                <Link
                    href={route('mrp.workflow.workcenter-planning.index', { workcenterCode: props.workcenterCode })}
                    prefetch
                    className={cn(
                        'inline-flex h-8 items-center justify-center gap-2 rounded-md px-3 text-xs font-bold transition-colors',
                        'bg-transparent text-green-700 hover:bg-green-100/50 hover:text-green-800',
                        'dark:text-[#68FF8E] dark:hover:bg-[#429356]/20 dark:hover:text-[#68FF8E]',
                    )}
                    title={'Steel Requirement Planning'}
                >
                    <WorkflowIcon className="h-3.5 w-3.5" />
                </Link>
                <Link
                    href={route('mrp.workflow.draft.index', { workcenterCode: props.workcenterCode })}
                    className={cn(
                        'inline-flex h-8 items-center justify-center gap-2 rounded-md px-2.5 text-xs font-semibold transition-colors',
                        'bg-transparent text-amber-600 hover:bg-amber-100/50 hover:text-amber-700',
                        'dark:text-yellow-400 dark:hover:bg-yellow-400/10 dark:hover:text-yellow-300',
                    )}
                    title={'Steel Requirement Planning (draft)'}
                >
                    <FileText className="h-3.5 w-3.5" />
                </Link>
            </ItemActions>
        </Item>
    );
}

export default function Index({ groupedWorkcenters }: IndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workflow" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <HeadingSmall title="Select Workcenter" description="Choose the press or production line to begin the planning sequence." />

                <div className="flex min-w-max flex-row justify-center gap-8 px-2 pb-6">
                    {groupedWorkcenters.map((group) => (
                        <div key={group.title} className="flex w-100 shrink-0 flex-col gap-4">
                            <header className="mb-6 flex items-center gap-2 px-1">
                                <div className="h-1 w-8 rounded-full bg-green-600 dark:bg-[#68FF8E]" />
                                <h3 className="text-xs font-bold tracking-widest text-green-900/60 uppercase dark:text-[#68FF8E]">{group.title}</h3>
                            </header>

                            <div className="flex flex-col gap-3">
                                {group.workcenters.map((item) => (
                                    <WorkcenterItem key={item.workcenter} {...item} />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}   
