import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

/**
 * Показва flash съобщенията от Laravel като toast.
 * Няма browser alert() никъде в приложението.
 */
export function FlashToasts() {
    const { flash } = usePage<SharedData>().props;
    const lastShown = useRef<string>('');

    useEffect(() => {
        if (!flash) {
            return;
        }

        const signature = JSON.stringify(flash);

        if (signature === lastShown.current) {
            return;
        }

        lastShown.current = signature;

        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }

        if (flash.info) {
            toast.info(flash.info);
        }
    }, [flash]);

    return null;
}
