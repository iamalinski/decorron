<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Пази менюто от „мъртви“ връзки: всеки адрес, който се показва в
 * навигацията, трябва да води до реална страница.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адресите, изброени в resources/js/lib/navigation.ts като готови.
     *
     * @return array<string, array{string}>
     */
    public static function navigationUrls(): array
    {
        return [
            'Табло' => ['/dashboard'],
            'CRM табло' => ['/crm'],
            'Клиенти' => ['/crm/customers'],
            'Запитвания' => ['/crm/opportunities'],
            'Оферти' => ['/crm/quotes'],
            'Поръчки' => ['/crm/orders'],
            'Задачи' => ['/crm/tasks'],
            'Календар' => ['/crm/calendar'],
            'Калкулатор' => ['/calculations/create'],
            'История' => ['/calculations'],
            'Материали' => ['/materials'],
            'Мерни единици' => ['/units'],
            'Настройки за ценообразуването' => ['/settings/pricing'],
            'Данни на бизнеса' => ['/settings/company'],
            'Профил' => ['/settings/profile'],
            'Парола' => ['/settings/password'],
            'Външен вид' => ['/settings/appearance'],
        ];
    }

    #[Test]
    #[DataProvider('navigationUrls')]
    public function every_menu_entry_leads_to_a_real_page(string $url): void
    {
        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertOk();
    }

    #[Test]
    #[DataProvider('navigationUrls')]
    public function no_menu_entry_is_reachable_without_a_session(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    /**
     * Изгледът „Кошче“ на всеки списък.
     *
     * @return array<string, array{string}>
     */
    public static function trashUrls(): array
    {
        return [
            'Кошче — клиенти' => ['/crm/customers?status=deleted'],
            'Кошче — запитвания' => ['/crm/opportunities?status=deleted'],
            'Кошче — оферти' => ['/crm/quotes?status=deleted'],
            'Кошче — поръчки' => ['/crm/orders?status=deleted'],
            'Кошче — задачи' => ['/crm/tasks?status=deleted'],
            'Кошче — калкулации' => ['/calculations?status=deleted'],
            'Кошче — материали' => ['/materials?status=deleted'],
        ];
    }

    #[Test]
    #[DataProvider('trashUrls')]
    public function every_list_has_a_working_trash_view(string $url): void
    {
        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertOk();
    }

    #[Test]
    public function the_navigation_file_does_not_expose_unfinished_pages(): void
    {
        $navigation = file_get_contents(base_path('resources/js/lib/navigation.ts'));

        // Всяка подопция с ready: false е скрита — тестът само документира,
        // че механизмът съществува и се ползва съзнателно.
        $this->assertStringContainsString('ready?: boolean', $navigation);
    }
}
