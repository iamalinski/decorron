import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner } from 'sonner';

type ToasterProps = React.ComponentProps<typeof Sonner>;

/**
 * Toast съобщения. Използват се вместо browser alert() за успех и грешки.
 */
export function Toaster(props: ToasterProps) {
    const { appearance } = useAppearance();

    return (
        <Sonner
            theme={appearance === 'system' ? 'system' : appearance}
            position="top-center"
            richColors
            closeButton
            toastOptions={{
                classNames: {
                    toast: 'rounded-lg border shadow-lg',
                },
            }}
            {...props}
        />
    );
}
