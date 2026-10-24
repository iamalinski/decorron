import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import * as React from 'react';

interface NumericInputProps extends Omit<React.ComponentProps<'input'>, 'onChange' | 'value' | 'type'> {
    value: string;
    onValueChange: (value: string) => void;
    /** Текст вдясно в полето, напр. „EUR“ или „бр.“ */
    suffix?: string;
    allowDecimals?: boolean;
}

/**
 * Числово поле, оптимизирано за мобилно въвеждане.
 *
 * Отваря цифровата клавиатура, приема десетична точка и запетая и пази
 * стойността като низ, за да не губи точност при финансови изчисления.
 */
export const NumericInput = React.forwardRef<HTMLInputElement, NumericInputProps>(
    ({ value, onValueChange, suffix, allowDecimals = true, className, ...props }, ref) => {
        const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
            const raw = event.target.value.replace(',', '.');

            if (raw === '') {
                onValueChange('');

                return;
            }

            const pattern = allowDecimals ? /^\d*\.?\d*$/ : /^\d*$/;

            if (pattern.test(raw)) {
                onValueChange(raw);
            }
        };

        return (
            <div className="relative">
                <Input
                    ref={ref}
                    type="text"
                    inputMode="decimal"
                    autoComplete="off"
                    value={value}
                    onChange={handleChange}
                    onFocus={(event) => event.currentTarget.select()}
                    className={cn('text-right tabular-nums', suffix && 'pr-12', className)}
                    {...props}
                />
                {suffix && (
                    <span className="text-muted-foreground pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm">{suffix}</span>
                )}
            </div>
        );
    },
);

NumericInput.displayName = 'NumericInput';
