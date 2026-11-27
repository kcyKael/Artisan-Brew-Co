<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ImagePreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingAndPublicMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_page_renders_transparent_hero_cup_intro_and_no_inline_scripts(): void
    {
        $category = Category::query()->create([
            'name' => 'Signature Lattes',
            'slug' => 'signature-lattes',
            'sort_order' => 1,
            'is_drink' => true,
            'is_active' => true,
        ]);

        $product = new Product([
            'name' => 'Spanish Latte',
            'category_id' => $category->id,
            'sku' => 'ABC-SIG-0001',
            'description' => 'Velvety espresso layered with condensed milk.',
            'price' => '175.00',
            'stock_quantity' => 12,
            'low_stock_threshold' => 4,
            'is_active' => true,
        ]);
        $product->save();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Crafted slowly.');
        $response->assertSee('Savored fully.');
        $response->assertSee('Spanish Latte');
        $response->assertSee('175.00');
        $response->assertSee('images/hero-coffee-cup.webp');
        $response->assertSee('hero-cup-shadow');
        $response->assertSee('hero-cup-steam');
        $response->assertSee('abc-intro-overlay');
        $response->assertSee('Skip intro');
        $response->assertSee('images/abc-logo.png');
        $response->assertDontSee('hero-cup-canvas');
        $this->assertSame(0, preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', (string) $response->getContent()));
    }

    public function test_public_menu_displays_active_products_and_excludes_archived_or_inactive_items(): void
    {
        $category = Category::query()->create([
            'name' => 'Espresso Bar',
            'slug' => 'espresso-bar',
            'sort_order' => 1,
            'is_drink' => true,
            'is_active' => true,
        ]);

        $active = new Product([
            'name' => 'Flat White',
            'category_id' => $category->id,
            'sku' => 'ABC-ESP-0001',
            'price' => '160.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ]);
        $active->save();

        $inactive = new Product([
            'name' => 'Hidden Secret Roast',
            'category_id' => $category->id,
            'sku' => 'ABC-ESP-0002',
            'price' => '210.00',
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'is_active' => false,
        ]);
        $inactive->save();

        $archived = new Product([
            'name' => 'Archived Winter Blend',
            'category_id' => $category->id,
            'sku' => 'ABC-ESP-0003',
            'price' => '195.00',
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);
        $archived->archived_at = now('UTC');
        $archived->save();

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('Flat White');
        $response->assertSee('160.00');
        $response->assertDontSee('Hidden Secret Roast');
        $response->assertDontSee('Archived Winter Blend');
    }

    public function test_public_guests_can_view_product_images_while_staff_avatars_remain_protected(): void
    {
        Storage::fake('local');

        $images = app(ImagePreparationService::class);
        $productUpload = UploadedFile::fake()->image('drink.jpg', 400, 400);
        $avatarUpload = UploadedFile::fake()->image('staff.jpg', 200, 200);

        $productPath = $images->prepareProductImage($productUpload);
        $avatarPath = $images->prepareAvatar($avatarUpload);

        $this->get(route('media.show', ['type' => 'products', 'filename' => basename($productPath)]))
            ->assertOk();

        $this->get(route('media.show', ['type' => 'avatars', 'filename' => basename($avatarPath)]))
            ->assertRedirect(route('login'));

        $staff = new User([
            'username' => 'avacashier',
            'full_name' => 'Ava Cashier',
            'password' => Hash::make('Secret@123'),
        ]);
        $staff->role = User::ROLE_CASHIER;
        $staff->is_active = true;
        $staff->save();

        $this->actingAs($staff)
            ->get(route('media.show', ['type' => 'avatars', 'filename' => basename($avatarPath)]))
            ->assertOk();
    }
}
