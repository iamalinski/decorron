<?php

declare(strict_types=1);

namespace Modules\Crm\Support;

/**
 * Единственото място, където живеят стойностите и българските етикети на
 * CRM номенклатурите. Backend-ът валидира срещу тях, а frontend-ът ги получава
 * като props — така няма как двата списъка да се разминат.
 */
final class CrmOptions
{
    /** Тип клиент. */
    public const CUSTOMER_TYPES = [
        'individual' => 'Физическо лице',
        'organization' => 'Организация',
    ];

    /** Откъде е дошъл клиентът или запитването. */
    public const SOURCES = [
        'phone' => 'Телефон',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'website' => 'Уебсайт',
        'referral' => 'Препоръка',
        'other' => 'Друго',
    ];

    /**
     * Етапи на продажбата — редът тук е редът в Kanban изгледа.
     *
     * Етапът описва докъде е стигнал разговорът. „Спечелена“ и „Загубена“
     * НЕ са етапи, а окончателни статуси (виж STATUSES).
     */
    public const STAGES = [
        'new' => 'Ново запитване',
        'consultation' => 'Консултация',
        'pricing' => 'Изготвяне на цена',
        'quote_sent' => 'Офертата е изпратена',
        'negotiation' => 'Очаква отговор',
    ];

    /** Окончателен статус на възможността. */
    public const STATUSES = [
        'open' => 'Отворена',
        'won' => 'Спечелена',
        'lost' => 'Загубена',
    ];

    /** Причини за загубена възможност. */
    public const LOST_REASONS = [
        'price' => 'Цена',
        'plans_changed' => 'Промяна на плановете',
        'competitor' => 'Избран е конкурент',
        'no_response' => 'Няма отговор',
        'other' => 'Друго',
    ];

    /**
     * Статус на офертата.
     *
     * „Изпратена“ се задава САМО когато потребителят потвърди, че е изпратил
     * офертата. Генерирането на PDF не променя статуса.
     */
    public const QUOTE_STATUSES = [
        'draft' => 'Чернова',
        'ready' => 'Готова за изпращане',
        'sent' => 'Изпратена',
        'accepted' => 'Приета',
        'declined' => 'Отказана',
        'expired' => 'Изтекла',
    ];

    /** Статус на изпълнение на поръчката. */
    public const ORDER_STATUSES = [
        'pending' => 'Очаква потвърждение',
        'confirmed' => 'Потвърдена',
        'preparing' => 'В подготовка',
        'in_progress' => 'В изпълнение',
        'completed' => 'Завършена',
        'cancelled' => 'Отменена',
    ];

    /** Вид задача. */
    public const TASK_TYPES = [
        'call' => 'Обаждане на клиент',
        'send_quote' => 'Изпращане на оферта',
        'follow_up' => 'Проследяване на отговор',
        'deposit' => 'Потвърждение на капаро',
        'prepare' => 'Подготовка на материали',
        'setup' => 'Монтаж',
        'other' => 'Друго',
    ];

    /** Вид отстъпка в офертата. */
    public const DISCOUNT_TYPES = [
        'percent' => 'Процент',
        'amount' => 'Сума',
    ];

    /**
     * Предложения за вид събитие. Полето е свободен текст — списъкът е само
     * за бързо попълване и не ограничава потребителя.
     */
    public const EVENT_TYPE_SUGGESTIONS = [
        'Сватба',
        'Рожден ден',
        'Кръщене',
        'Фирмено събитие',
        'Годишнина',
        'Абитуриентски бал',
        'Бебешко парти',
    ];

    /** @return array<int, string> */
    public static function customerTypeValues(): array
    {
        return array_keys(self::CUSTOMER_TYPES);
    }

    /** @return array<int, string> */
    public static function stageValues(): array
    {
        return array_keys(self::STAGES);
    }

    /** @return array<int, string> */
    public static function statusValues(): array
    {
        return array_keys(self::STATUSES);
    }

    /** @return array<int, string> */
    public static function lostReasonValues(): array
    {
        return array_keys(self::LOST_REASONS);
    }

    /** @return array<int, string> */
    public static function quoteStatusValues(): array
    {
        return array_keys(self::QUOTE_STATUSES);
    }

    /** @return array<int, string> */
    public static function orderStatusValues(): array
    {
        return array_keys(self::ORDER_STATUSES);
    }

    /** @return array<int, string> */
    public static function taskTypeValues(): array
    {
        return array_keys(self::TASK_TYPES);
    }

    /** @return array<int, string> */
    public static function discountTypeValues(): array
    {
        return array_keys(self::DISCOUNT_TYPES);
    }

    /** @return array<int, string> */
    public static function sourceValues(): array
    {
        return array_keys(self::SOURCES);
    }

    /**
     * Номенклатурите във вида, в който ги очаква React.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public static function forForms(): array
    {
        return [
            'customer_types' => self::pairs(self::CUSTOMER_TYPES),
            'sources' => self::pairs(self::SOURCES),
            'stages' => self::pairs(self::STAGES),
            'statuses' => self::pairs(self::STATUSES),
            'lost_reasons' => self::pairs(self::LOST_REASONS),
            'quote_statuses' => self::pairs(self::QUOTE_STATUSES),
            'discount_types' => self::pairs(self::DISCOUNT_TYPES),
            'order_statuses' => self::pairs(self::ORDER_STATUSES),
            'task_types' => self::pairs(self::TASK_TYPES),
            'event_types' => array_map(
                static fn (string $label): array => ['value' => $label, 'label' => $label],
                self::EVENT_TYPE_SUGGESTIONS,
            ),
        ];
    }

    /**
     * @param  array<string, string>  $map
     * @return array<int, array{value: string, label: string}>
     */
    public static function pairs(array $map): array
    {
        return array_map(
            static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys($map),
            array_values($map),
        );
    }
}
