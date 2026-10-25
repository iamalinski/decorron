import { Button } from '@/components/ui/button';
import { formatDate, formatMoney } from '@/lib/money';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useEffect } from 'react';
import type { CompanyDetails, Quote } from '../../types';

interface QuotePrintProps {
    quote: { data: Quote };
    company: CompanyDetails;
}

/**
 * Клиентският изглед на офертата за печат или запис като PDF.
 *
 * Тук НЯМА себестойност, марж или печалба — документът е предназначен за
 * клиента. Отварянето на този екран не променя статуса на офертата.
 */
export default function QuotePrint({ quote: wrapper, company }: QuotePrintProps) {
    const quote = wrapper.data;
    const currency = quote.currency;
    const items = quote.items ?? [];

    // Изгледът за печат е винаги светъл, независимо от избраната тема.
    useEffect(() => {
        const wasDark = document.documentElement.classList.contains('dark');

        document.documentElement.classList.remove('dark');
        document.body.classList.add('bg-white');

        return () => {
            document.documentElement.classList.toggle('dark', wasDark);
            document.body.classList.remove('bg-white');
        };
    }, []);

    return (
        <div className="min-h-svh bg-white text-black">
            <Head title={`Оферта ${quote.quote_number}`} />

            <div className="mx-auto max-w-3xl px-6 py-6 print:px-0 print:py-0">
                <div className="mb-6 flex items-center justify-between gap-3 print:hidden">
                    <Button asChild variant="outline" size="sm">
                        <Link href={route('crm.quotes.show', quote.id)}>
                            <ArrowLeft aria-hidden="true" /> Назад
                        </Link>
                    </Button>
                    <Button size="sm" onClick={() => window.print()}>
                        <Printer aria-hidden="true" /> Печат / запази като PDF
                    </Button>
                </div>

                <header className="flex flex-wrap items-start justify-between gap-4 border-b border-gray-300 pb-4">
                    <div>
                        <p className="text-lg font-semibold">{company.name}</p>
                        <div className="mt-1 text-xs text-gray-600">
                            {company.phone && <p>Тел.: {company.phone}</p>}
                            {company.email && <p>Имейл: {company.email}</p>}
                            {company.address && <p>{company.address}</p>}
                            {company.extra && <p>{company.extra}</p>}
                        </div>
                    </div>
                    <div className="text-right">
                        <p className="text-base font-semibold">Оферта {quote.quote_number}</p>
                        <p className="text-xs text-gray-600">Версия {quote.version}</p>
                        <p className="text-xs text-gray-600">Дата: {formatDate(quote.issued_at)}</p>
                        {quote.valid_until && <p className="text-xs text-gray-600">Валидна до: {formatDate(quote.valid_until)}</p>}
                    </div>
                </header>

                <section className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-xs font-medium tracking-wide text-gray-500 uppercase">До</p>
                        <p className="font-medium">{quote.customer_name}</p>
                        {quote.customer_contact && <p className="text-xs text-gray-600">На вниманието на: {quote.customer_contact}</p>}
                        {quote.customer_phone && <p className="text-xs text-gray-600">Тел.: {quote.customer_phone}</p>}
                        {quote.customer_email && <p className="text-xs text-gray-600">{quote.customer_email}</p>}
                        {quote.customer_address && <p className="text-xs text-gray-600">{quote.customer_address}</p>}
                    </div>

                    {(quote.event_date || quote.event_location) && (
                        <div>
                            <p className="text-xs font-medium tracking-wide text-gray-500 uppercase">Събитие</p>
                            {quote.event_date && <p>{formatDate(quote.event_date)}</p>}
                            {quote.event_location && <p className="text-xs text-gray-600">{quote.event_location}</p>}
                        </div>
                    )}
                </section>

                <table className="mt-6 w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-y border-gray-300 text-left">
                            <th className="py-2">Услуга</th>
                            <th className="py-2 text-right">Кол.</th>
                            <th className="py-2 text-right">Ед. цена</th>
                            <th className="py-2 text-right">Сума</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={item.id} className="border-b border-gray-200 align-top">
                                <td className="py-2">
                                    {item.description}
                                    {item.details && <p className="text-xs text-gray-600">{item.details}</p>}
                                </td>
                                <td className="py-2 text-right tabular-nums">{Number(item.quantity)}</td>
                                <td className="py-2 text-right tabular-nums">{formatMoney(item.unit_price, currency)}</td>
                                <td className="py-2 text-right tabular-nums">{formatMoney(item.line_total, currency)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mt-4 ml-auto max-w-xs text-sm">
                    <div className="flex justify-between py-1">
                        <span className="text-gray-600">Междинна сума</span>
                        <span className="tabular-nums">{formatMoney(quote.subtotal, currency)}</span>
                    </div>
                    {Number(quote.discount_amount) > 0 && (
                        <div className="flex justify-between py-1">
                            <span className="text-gray-600">Отстъпка</span>
                            <span className="tabular-nums">−{formatMoney(quote.discount_amount, currency)}</span>
                        </div>
                    )}
                    <div className="flex justify-between border-t border-gray-300 py-2 text-base font-semibold">
                        <span>Общо</span>
                        <span className="tabular-nums">{formatMoney(quote.total, currency)}</span>
                    </div>
                    {quote.deposit_amount && (
                        <div className="flex justify-between py-1">
                            <span className="text-gray-600">Капаро</span>
                            <span className="tabular-nums">{formatMoney(quote.deposit_amount, currency)}</span>
                        </div>
                    )}
                </div>

                {Number(quote.vat_rate) === 0 && (
                    <p className="mt-4 text-xs text-gray-600">
                        Сумите са крайни. Не се начислява ДДС — доставчикът не е регистриран по ЗДДС.
                    </p>
                )}

                {quote.terms && (
                    <section className="mt-6 border-t border-gray-300 pt-4">
                        <p className="text-xs font-medium tracking-wide text-gray-500 uppercase">Условия</p>
                        <p className="mt-1 text-sm whitespace-pre-wrap">{quote.terms}</p>
                    </section>
                )}

                <p className="mt-8 text-xs text-gray-500">
                    Документът е оферта, а не фактура. Валиден е за посочения срок.
                </p>
            </div>
        </div>
    );
}
