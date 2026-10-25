import type { Trashable } from '@/types';

/** Номенклатура „стойност → етикет“, която идва от backend-а. */
export interface Option {
    value: string;
    label: string;
}

export interface CrmOptions {
    customer_types: Option[];
    sources: Option[];
    stages: Option[];
    statuses: Option[];
    lost_reasons: Option[];
    quote_statuses: Option[];
    discount_types: Option[];
    order_statuses: Option[];
    task_types: Option[];
    event_types: Option[];
}

export interface QuoteItem {
    id?: number;
    calculation_id: number | null;
    description: string;
    details: string | null;
    quantity: string;
    unit_price: string;
    line_total: string;
    position: number;
}

export interface QuoteVersion {
    id: number;
    version: number;
    total: string;
    sent_at: string | null;
    payload: Record<string, unknown>;
}

export interface Quote extends Trashable {
    id: number;
    quote_number: string;
    status: string;
    status_label: string;
    version: number;
    is_editable: boolean;
    was_sent: boolean;
    customer_id: number;
    crm_opportunity_id: number | null;
    opportunity_title?: string | null;
    issued_at: string;
    valid_until: string | null;
    event_date: string | null;
    event_location: string | null;
    customer_name: string;
    customer_contact: string | null;
    customer_phone: string | null;
    customer_email: string | null;
    customer_address: string | null;
    subtotal: string;
    discount_type: string | null;
    discount_value: string | null;
    discount_amount: string;
    total: string;
    deposit_amount: string | null;
    vat_rate: string;
    currency: string;
    terms: string | null;
    internal_notes: string | null;
    sent_at: string | null;
    accepted_at: string | null;
    declined_at: string | null;
    created_at?: string;
    items?: QuoteItem[];
    versions?: QuoteVersion[];
}

export interface OrderItem {
    id?: number;
    description: string;
    details: string | null;
    quantity: string;
    unit_price: string;
    line_total: string;
    position: number;
}

export interface Order extends Trashable {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    customer_id: number;
    customer_name: string;
    customer_phone: string | null;
    customer_email: string | null;
    crm_opportunity_id: number | null;
    opportunity_title?: string | null;
    quote_id: number | null;
    quote_number?: string | null;
    quote_version: number | null;
    event_date: string | null;
    event_location: string | null;
    agreed_total: string;
    deposit_amount: string | null;
    currency: string;
    execution_notes: string | null;
    deadline_note: string | null;
    confirmed_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at?: string;
    items?: OrderItem[];
}

export interface Task extends Trashable {
    id: number;
    title: string;
    description: string | null;
    type: string;
    type_label: string;
    due_on: string | null;
    completed_at: string | null;
    is_overdue: boolean;
    customer_id: number | null;
    customer_name?: string | null;
    crm_opportunity_id: number | null;
    opportunity_title?: string | null;
    quote_id: number | null;
    order_id: number | null;
    created_at?: string;
}

/** Запис в календара — поръчка, запитване, оферта или задача. */
export interface CalendarEntry {
    kind: 'order' | 'opportunity' | 'quote' | 'task';
    date: string;
    title: string;
    subtitle: string | null;
    url: string;
}

export interface CompanyDetails {
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    extra: string | null;
}

export interface ActivityLog {
    id: number;
    type: string;
    description: string;
    is_automatic: boolean;
    meta: Record<string, unknown> | null;
    opportunity_id: number | null;
    customer_id: number | null;
    created_at: string;
}

export interface Opportunity extends Trashable {
    id: number;
    customer_id: number;
    customer_name?: string;
    customer_phone?: string | null;
    title: string;
    event_type: string | null;
    event_date: string | null;
    event_location: string | null;
    expected_budget: string | null;
    source: string | null;
    source_label: string | null;
    stage: string;
    stage_label: string;
    status: string;
    status_label: string;
    lost_reason: string | null;
    lost_reason_label: string | null;
    lost_reason_note: string | null;
    next_action: string | null;
    next_action_due: string | null;
    notes: string | null;
    closed_at: string | null;
    created_at?: string;
    updated_at?: string;
    activities?: ActivityLog[];
}

export interface OpportunityFormValues {
    customer_id: string;
    title: string;
    event_type: string;
    event_date: string;
    event_location: string;
    expected_budget: string;
    source: string;
    stage: string;
    next_action: string;
    next_action_due: string;
    notes: string;
    [key: string]: string;
}

export function emptyOpportunityForm(customerId?: number): OpportunityFormValues {
    return {
        customer_id: customerId ? String(customerId) : '',
        title: '',
        event_type: '',
        event_date: '',
        event_location: '',
        expected_budget: '',
        source: '',
        stage: 'new',
        next_action: '',
        next_action_due: '',
        notes: '',
    };
}

export function opportunityToForm(opportunity: Opportunity): OpportunityFormValues {
    return {
        customer_id: String(opportunity.customer_id),
        title: opportunity.title,
        event_type: opportunity.event_type ?? '',
        event_date: opportunity.event_date ?? '',
        event_location: opportunity.event_location ?? '',
        expected_budget: opportunity.expected_budget ?? '',
        source: opportunity.source ?? '',
        stage: opportunity.stage,
        next_action: opportunity.next_action ?? '',
        next_action_due: opportunity.next_action_due ?? '',
        notes: opportunity.notes ?? '',
    };
}

export interface Customer extends Trashable {
    id: number;
    type: string;
    type_label: string;
    name: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    address: string | null;
    source: string | null;
    source_label: string | null;
    notes: string | null;
    is_archived: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface CustomerFormValues {
    type: string;
    name: string;
    contact_person: string;
    phone: string;
    email: string;
    city: string;
    address: string;
    source: string;
    notes: string;
    confirm_duplicate: boolean;
    [key: string]: string | boolean;
}

export function emptyCustomerForm(): CustomerFormValues {
    return {
        type: 'individual',
        name: '',
        contact_person: '',
        phone: '',
        email: '',
        city: '',
        address: '',
        source: '',
        notes: '',
        confirm_duplicate: false,
    };
}

export function customerToForm(customer: Customer): CustomerFormValues {
    return {
        type: customer.type,
        name: customer.name,
        contact_person: customer.contact_person ?? '',
        phone: customer.phone ?? '',
        email: customer.email ?? '',
        city: customer.city ?? '',
        address: customer.address ?? '',
        source: customer.source ?? '',
        notes: customer.notes ?? '',
        confirm_duplicate: false,
    };
}
