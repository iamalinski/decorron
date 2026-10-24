<?php

declare(strict_types=1);

namespace Modules\Catalog\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Database\Seeders\CatalogSeeder;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function guests_cannot_reach_the_catalog(): void
    {
        $this->get(route('materials.index'))->assertRedirect(route('login'));
        $this->get(route('units.index'))->assertRedirect(route('login'));
        $this->post(route('materials.store'), [])->assertRedirect(route('login'));
    }

    #[Test]
    public function a_new_user_receives_the_default_units(): void
    {
        $user = User::factory()->create();

        event(new Registered($user));

        $this->assertSame(count(CatalogSeeder::DEFAULT_UNITS), Unit::query()->ownedBy($user)->count());
        $this->assertDatabaseHas('units', ['user_id' => $user->id, 'symbol' => 'бр.', 'name' => 'Брой']);
        $this->assertDatabaseHas('units', ['user_id' => $user->id, 'symbol' => 'кв. м.']);
    }

    #[Test]
    public function a_unit_can_be_created_updated_and_archived(): void
    {
        $this->actingAs($this->user)
            ->post(route('units.store'), ['name' => 'Ролка', 'symbol' => 'ролка', 'description' => null])
            ->assertRedirect();

        $unit = Unit::query()->firstOrFail();
        $this->assertTrue($unit->is_active);

        $this->actingAs($this->user)
            ->patch(route('units.update', $unit), ['name' => 'Ролка лента', 'symbol' => 'ролка', 'description' => 'за арки'])
            ->assertRedirect();

        $this->assertSame('Ролка лента', $unit->refresh()->name);

        $this->actingAs($this->user)->patch(route('units.archive', $unit))->assertRedirect();
        $this->assertFalse($unit->refresh()->is_active);

        $this->actingAs($this->user)->patch(route('units.archive', $unit))->assertRedirect();
        $this->assertTrue($unit->refresh()->is_active);
    }

    #[Test]
    public function unit_symbols_must_be_unique_per_user(): void
    {
        Unit::factory()->create(['user_id' => $this->user->id, 'name' => 'Брой', 'symbol' => 'бр.']);

        $this->actingAs($this->user)
            ->post(route('units.store'), ['name' => 'Броя', 'symbol' => 'бр.'])
            ->assertSessionHasErrors('symbol');

        // Друг потребител може да използва същия символ.
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post(route('units.store'), ['name' => 'Брой', 'symbol' => 'бр.'])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_material_can_be_created_and_updated(): void
    {
        $unit = Unit::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->post(route('materials.store'), [
            'name' => 'Балони хром',
            'sku' => 'BAL-CHROME',
            'category' => 'Балони',
            'unit_id' => $unit->id,
            'default_unit_price' => '1.25',
            'description' => null,
        ])->assertRedirect();

        $material = Material::query()->firstOrFail();

        $this->assertSame('1.250000', $material->default_unit_price);
        $this->assertSame($this->user->id, $material->user_id);

        $this->actingAs($this->user)->patch(route('materials.update', $material), [
            'name' => 'Балони хром 30 см',
            'sku' => 'BAL-CHROME',
            'category' => 'Балони',
            'unit_id' => $unit->id,
            'default_unit_price' => '1.40',
            'description' => null,
        ])->assertRedirect();

        $this->assertSame('Балони хром 30 см', $material->refresh()->name);
    }

    #[Test]
    public function a_material_sku_must_be_unique_per_user(): void
    {
        $unit = Unit::factory()->create(['user_id' => $this->user->id]);
        Material::factory()->create(['user_id' => $this->user->id, 'unit_id' => $unit->id, 'sku' => 'DUP-1']);

        $this->actingAs($this->user)->post(route('materials.store'), [
            'name' => 'Друг материал',
            'sku' => 'DUP-1',
            'unit_id' => $unit->id,
            'default_unit_price' => '1',
        ])->assertSessionHasErrors('sku');
    }

    #[Test]
    public function a_material_cannot_use_another_users_unit(): void
    {
        $foreignUnit = Unit::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->post(route('materials.store'), [
            'name' => 'Балони',
            'unit_id' => $foreignUnit->id,
            'default_unit_price' => '1',
        ])->assertSessionHasErrors('unit_id');
    }

    #[Test]
    public function the_quick_add_endpoint_returns_json(): void
    {
        $unit = Unit::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson(route('materials.store'), [
                'name' => 'Лепящи точки',
                'unit_id' => $unit->id,
                'default_unit_price' => '2.5',
            ])
            ->assertCreated()
            ->assertJsonPath('material.name', 'Лепящи точки');
    }

    #[Test]
    public function the_search_endpoint_only_returns_active_materials_of_the_current_user(): void
    {
        $unit = Unit::factory()->create(['user_id' => $this->user->id]);
        Material::factory()->create(['user_id' => $this->user->id, 'unit_id' => $unit->id, 'name' => 'Балони хром']);
        Material::factory()->archived()->create(['user_id' => $this->user->id, 'unit_id' => $unit->id, 'name' => 'Балони архив']);

        $foreignUnit = Unit::factory()->create(['user_id' => User::factory()->create()->id]);
        Material::factory()->create(['user_id' => $foreignUnit->user_id, 'unit_id' => $foreignUnit->id, 'name' => 'Балони чужди']);

        $response = $this->actingAs($this->user)->getJson(route('materials.search', ['q' => 'Балони']));

        $response->assertOk()->assertJsonCount(1, 'materials');
        $response->assertJsonPath('materials.0.name', 'Балони хром');
    }

    #[Test]
    public function a_user_cannot_update_or_archive_another_users_material(): void
    {
        $unit = Unit::factory()->create(['user_id' => $this->user->id]);
        $material = Material::factory()->create(['user_id' => $this->user->id, 'unit_id' => $unit->id]);

        $intruder = User::factory()->create();
        $intruderUnit = Unit::factory()->create(['user_id' => $intruder->id]);

        $this->actingAs($intruder)->patch(route('materials.update', $material), [
            'name' => 'Хакнато',
            'unit_id' => $intruderUnit->id,
            'default_unit_price' => '1',
        ])->assertForbidden();

        $this->actingAs($intruder)->patch(route('materials.archive', $material))->assertForbidden();

        $this->assertNotSame('Хакнато', $material->refresh()->name);
    }

    #[Test]
    public function the_catalog_index_filters_by_status(): void
    {
        $unit = Unit::factory()->create(['user_id' => $this->user->id]);
        Material::factory()->create(['user_id' => $this->user->id, 'unit_id' => $unit->id]);
        Material::factory()->archived()->create(['user_id' => $this->user->id, 'unit_id' => $unit->id]);

        $this->actingAs($this->user)
            ->get(route('materials.index', ['status' => 'archived']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('catalog::materials/index')->has('materials.data', 1));

        $this->actingAs($this->user)
            ->get(route('materials.index', ['status' => 'all']))
            ->assertInertia(fn ($page) => $page->has('materials.data', 2));
    }
}
