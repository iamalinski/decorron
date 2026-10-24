import type { InputMode } from '@/lib/pricing';

/** Един материален ред в екрана за калкулация. */
export interface LineState {
    /** Стабилен ключ за React — не се изпраща към сървъра. */
    key: string;
    material_id: number | null;
    material_name: string;
    sku: string | null;
    unit_id: string;
    quantity: string;
    input_mode: InputMode;
    entered_total_cost: string;
    unit_price: string;
}

let counter = 0;

export function newLine(unitId: string | number | null = ''): LineState {
    counter += 1;

    return {
        key: `line-${Date.now()}-${counter}`,
        material_id: null,
        material_name: '',
        sku: null,
        unit_id: unitId === null ? '' : String(unitId),
        quantity: '',
        input_mode: 'total',
        entered_total_cost: '',
        unit_price: '',
    };
}
