<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;

/**
 * Начални мерни единици и примерни материали за нов потребител.
 */
class CatalogSeeder extends Seeder
{
    /** @var list<array{name: string, symbol: string}> */
    public const DEFAULT_UNITS = [
        ['name' => 'Брой', 'symbol' => 'бр.'],
        ['name' => 'Килограм', 'symbol' => 'кг.'],
        ['name' => 'Грам', 'symbol' => 'г.'],
        ['name' => 'Литър', 'symbol' => 'л.'],
        ['name' => 'Милилитър', 'symbol' => 'мл.'],
        ['name' => 'Сантиметър', 'symbol' => 'см.'],
        ['name' => 'Метър', 'symbol' => 'м.'],
        ['name' => 'Квадратен метър', 'symbol' => 'кв. м.'],
        ['name' => 'Комплект', 'symbol' => 'комплект'],
        ['name' => 'Пакет', 'symbol' => 'пакет'],
        ['name' => 'Ролка', 'symbol' => 'ролка'],
    ];

    public function run(?User $user = null): void
    {
        $user ??= User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->command?->warn('Няма потребител — пропускам каталога.');

            return;
        }

        $units = self::seedUnitsFor($user);

        $piece = $units['бр.'];
        $meter = $units['м.'];
        $pack = $units['пакет'];

        $materials = [
            ['Балони хром', 'BAL-CHROME', 'Балони', $piece, '1.000000'],
            ['Балони пастел', 'BAL-PASTEL', 'Балони', $piece, '0.600000'],
            ['Балони конфети', 'BAL-CONF', 'Балони', $piece, '1.200000'],
            ['Фолиеви цифри', 'BAL-NUM', 'Балони', $piece, '4.500000'],
            ['Лента за арка', 'ARC-STRIP', 'Консумативи', $meter, '0.800000'],
            ['Тюл', 'TEX-TULLE', 'Текстил', $meter, '1.500000'],
            ['Лепящи точки', 'CON-DOTS', 'Консумативи', $pack, '2.500000'],
        ];

        foreach ($materials as [$name, $sku, $category, $unit, $price]) {
            $material = Material::query()
                ->where('user_id', $user->getKey())
                ->where('sku', $sku)
                ->first() ?? new Material;

            $material->fill([
                'name' => $name,
                'sku' => $sku,
                'category' => $category,
                'unit_id' => $unit->getKey(),
                'default_unit_price' => $price,
                'is_active' => true,
            ]);

            // user_id не е fillable, за да не може да бъде подменен от заявка.
            $material->user_id = $user->getKey();
            $material->save();
        }
    }

    /**
     * Създава липсващите стандартни мерни единици за потребителя.
     *
     * @return array<string, Unit> индексирани по символ
     */
    public static function seedUnitsFor(User $user): array
    {
        $units = [];

        foreach (self::DEFAULT_UNITS as $definition) {
            $unit = Unit::query()
                ->where('user_id', $user->getKey())
                ->where('symbol', $definition['symbol'])
                ->first();

            if ($unit === null) {
                $unit = new Unit(['name' => $definition['name'], 'symbol' => $definition['symbol'], 'is_active' => true]);
                $unit->user_id = $user->getKey();
                $unit->save();
            }

            $units[$definition['symbol']] = $unit;
        }

        return $units;
    }
}
