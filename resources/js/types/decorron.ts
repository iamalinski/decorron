import type { InputMode, TaxRates } from '@/lib/pricing';

export type { InputMode, TaxRates };

export interface Unit {
    id: number;
    name: string;
    symbol: string;
    description: string | null;
    is_active: boolean;
    materials_count?: number;
    created_at?: string;
}

export interface Material {
    id: number;
    name: string;
    sku: string | null;
    category: string | null;
    unit_id: number;
    unit_name?: string;
    unit_symbol?: string;
    default_unit_price: string;
    description: string | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface CalculationItem {
    id: number;
    material_id: number | null;
    unit_id: number | null;
    material_name: string;
    sku: string | null;
    unit_name: string;
    unit_symbol: string;
    quantity: string;
    input_mode: InputMode;
    entered_total_cost: string | null;
    unit_price: string;
    line_total: string;
    position: number;
}

export interface Calculation {
    id: number;
    calculation_number: string;
    service_name: string;
    client_name: string | null;
    notes: string | null;
    calculated_at: string;
    material_cost_total: string;
    labor_cost: string;
    other_costs: string;
    total_cost: string;
    target_net_margin: string;
    corporate_tax_rate_snapshot: string;
    dividend_tax_rate_snapshot: string;
    vat_rate_snapshot: string;
    profit_before_tax: string;
    corporate_tax_amount: string;
    profit_after_corporate_tax: string;
    dividend_tax_amount: string;
    net_profit: string;
    final_price: string;
    actual_net_margin: string;
    price_was_rounded: boolean;
    currency: string;
    created_at?: string;
    items?: CalculationItem[];
    items_count?: number;
}

export interface CalculationDefaults {
    net_margin_percent: number;
    rounding_strategy: string;
    calculated_at: string;
    default_unit_id: number | null;
}
