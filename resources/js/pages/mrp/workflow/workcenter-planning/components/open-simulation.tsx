import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { Loader2, RotateCcw, Workflow, WorkflowIcon } from "lucide-react";
import { useState } from "react";
import { ProductionPlanning } from "..";
import { Button } from "@/components/ui/button";

interface OpenSimulationProps {
    open: boolean;
    handleOpenChange: (open: boolean) => void;
    selectedProductionPlanning?: ProductionPlanning | null;
}

export default function OpenSimulation({ open, handleOpenChange, selectedProductionPlanning }: OpenSimulationProps) {
    const [isPlanning, setIsPlanning] = useState(false);

    const handlePlan = () => {
        router.get(route('mrp.simulation.index', [selectedProductionPlanning?.productionPlanningId]), {}, {
            preserveState: false,
            preserveScroll: false,
            onStart: () => {
                setIsPlanning(true);
            },
            onFinish: () => {
                setIsPlanning(false);
            }
        })
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent
                className={cn(
                    "sm:max-w-[450px] max-h-[90vh] overflow-hidden flex flex-col p-0 gap-0",
                    "border-2 border-[#68FF8E]",
                    "shadow-[0_0_15px_rgba(104,255,142,0.35)]"
                )}
                aria-busy={isPlanning}
                onEscapeKeyDown={(e) => {
                    if (isPlanning) e.preventDefault();
                }}
                onPointerDownOutside={(e) => {
                    if (isPlanning) e.preventDefault();
                }}
            >
                {/* State Overlay */}
                {isPlanning && (
                    <div
                        className="absolute inset-0 z-50 flex items-center justify-center rounded-lg bg-background/85 backdrop-blur-sm transition-all"
                        aria-live="polite"
                    >
                        <div className="flex flex-col items-center gap-3 p-6 text-center">
                            <Loader2 className="h-8 w-8 animate-spin text-[#68FF8E]" />
                            <div className="space-y-1">
                                <p className="text-sm font-semibold text-foreground">
                                    Calculating Material Plan
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Please wait while the planning scenario is being calculated.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Modal Header */}
                <DialogHeader className="p-6 pb-4 border-b border-border/60">
                    <div className="flex items-start gap-3 text-left">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#68FF8E]/10 ring-1 ring-[#68FF8E]/30">
                            <Workflow className="h-5 w-5 text-[#429356] dark:text-[#68FF8E]" />
                        </div>

                        <div className="space-y-1">
                            <DialogTitle className="text-base font-semibold tracking-tight">
                                Material Planning
                            </DialogTitle>
                            <DialogDescription className="text-xs leading-relaxed text-muted-foreground">
                                Simulate material requirements and review the tooling plan before applying any changes.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                {/* Main Content Area (añadir aquí tus inputs o cuerpo del modal) */}
                <div className="flex-1 overflow-y-auto p-6 text-sm">
                    {/* Tu formulario / checklist / contenido del diálogo */}
                </div>

                {/* Modal Footer */}
                <DialogFooter className="p-6 pt-4 border-t border-border/60 bg-muted/20">
                    <Button
                        type="button"
                        variant="outline"
                        size={'sm'}
                        disabled={isPlanning}
                        onClick={() => handleOpenChange(false)}
                    >
                        Cancel
                    </Button>

                    <Button
                        type="button"
                        disabled={isPlanning}
                        onClick={handlePlan}
                        size={'sm'}
                        className={cn(
                            "bg-[#429356] text-white hover:bg-[#368247]",
                            "dark:bg-[#429356] dark:hover:bg-[#50F178] dark:hover:text-black",
                            "transition-colors"
                        )}
                    >
                        {isPlanning ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Calculating...
                            </>
                        ) : (
                            <>
                                <Workflow className="mr-2 h-4 w-4" />
                                Simulate & Plan
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}