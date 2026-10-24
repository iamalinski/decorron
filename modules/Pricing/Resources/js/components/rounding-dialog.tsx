import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatMoney, formatPercent } from '@/lib/money';
import { computeBreakdown, roundingOptions, type BreakdownInput } from '@/lib/pricing';
import Big from 'big.js';
import { ArrowRight } from 'lucide-react';

interface RoundingDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Изходната (неокръглена) цена. */
    basePrice: Big;
    baseNetProfit: Big;
    baseMargin: Big;
    /** Входът, с който се преизчисляват вариантите. */
    input: BreakdownInput;
    currency: string;
    onApply: (price: string | null) => void;
    isApplied: boolean;
}

/**
 * Предлага клиентска цена (цяло евро, на 5 или на 9) и показва как се
 * променят чистата печалба и маржът. Не променя въведените разходи.
 */
export function RoundingDialog({
    open,
    onOpenChange,
    basePrice,
    baseNetProfit,
    baseMargin,
    input,
    currency,
    onApply,
    isApplied,
}: RoundingDialogProps) {
    const options = roundingOptions(basePrice).map((option) => {
        const result = computeBreakdown({ ...input, finalPriceOverride: option.price.toFixed(2) });

        return {
            ...option,
            netProfit: result.ok ? result.netProfit : new Big(0),
            margin: result.ok ? result.actualNetMargin : new Big(0),
            valid: result.ok,
        };
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Закръгляване на цената</DialogTitle>
                    <DialogDescription>
                        Изходна цена {formatMoney(basePrice, currency)} · чиста печалба {formatMoney(baseNetProfit, currency)} (
                        {formatPercent(baseMargin)}). Разходите остават непроменени.
                    </DialogDescription>
                </DialogHeader>

                <ul className="grid gap-2">
                    {options.map((option) => (
                        <li key={option.key}>
                            <button
                                type="button"
                                onClick={() => {
                                    onApply(option.price.toFixed(2));
                                    onOpenChange(false);
                                }}
                                disabled={!option.valid}
                                className="flex w-full items-center justify-between gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-accent focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm text-muted-foreground">{option.label}</p>
                                    <p className="text-lg font-semibold tabular-nums">{formatMoney(option.price, currency)}</p>
                                </div>
                                <div className="shrink-0 text-right text-xs text-muted-foreground">
                                    <p className="flex items-center justify-end gap-1">
                                        {formatMoney(baseNetProfit, currency)} <ArrowRight className="size-3" aria-hidden="true" />
                                        <span className="font-medium text-foreground">{formatMoney(option.netProfit, currency)}</span>
                                    </p>
                                    <p>марж {formatPercent(option.margin)}</p>
                                </div>
                            </button>
                        </li>
                    ))}
                </ul>

                <DialogFooter>
                    {isApplied && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                onApply(null);
                                onOpenChange(false);
                            }}
                        >
                            Върни изчислената цена
                        </Button>
                    )}
                    <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                        Затвори
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
