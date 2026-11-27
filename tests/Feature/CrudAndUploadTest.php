<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrudAndUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $username = 'admin_crud'): User
    {
        $admin = new User([
            'username' => $username,
            'full_name' => 'Admin User',
            'password' => Hash::make('Admin@1234'),
        ]);
        $admin->role = User::ROLE_ADMIN;
        $admin->is_active = true;
        $admin->save();

        return $admin;
    }

    private function makeRealJpegUpload(string $filename = 'latte.jpg'): UploadedFile
    {
        $img = imagecreatetruecolor(120, 90);
        $bg = imagecolorallocate($img, 198, 166, 107);
        imagefill($img, 0, 0, $bg);
        $tmpPath = tempnam(sys_get_temp_dir(), 'abc_test_img_').'.jpg';
        imagejpeg($img, $tmpPath, 85);
        imagedestroy($img);

        return new UploadedFile($tmpPath, $filename, 'image/jpeg', null, true);
    }

    public function test_product_crud_image_preparation_and_disguised_script_rejection(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $category = Category::create([
            'name' => 'Espresso Bar',
            'slug' => 'espresso-bar',
            'sort_order' => 1,
            'is_drink' => true,
            'is_active' => true,
        ]);

        $fakePhp = UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "pwn"; ?>');
        $this->post('/products', [
            'name' => 'Bad Item',
            'category_id' => $category->id,
            'price' => '150.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => 1,
            'image' => $fakePhp,
        ])->assertSessionHasErrors('image');

        $this->post('/products', [
            'name' => 'Velvet Latte',
            'category_id' => $category->id,
            'sku' => 'ABC-TEST-01',
            'price' => '165.00',
            'stock_quantity' => 12,
            'low_stock_threshold' => 3,
            'is_active' => 1,
            'image' => $this->makeRealJpegUpload(),
        ])->assertRedirect();

        $product = Product::where('name', 'Velvet Latte')->firstOrFail();
        $this->assertNotNull($product->image);
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('local')->assertExists('media/'.$product->image);

        $this->get($product->imageUrl())->assertOk();

        $imgPath = 'media/'.$product->image;
        $this->delete("/products/{$product->id}")->assertRedirect('/products');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('local')->assertMissing($imgPath);
    }

    public function test_referenced_product_archives_and_stale_edit_cannot_overwrite_sale_decrement(): void
    {
        $admin = $this->makeAdmin();
        $product = Product::create([
            'name' => 'Caramel Latte',
            'price' => '170.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ])->refresh();

        $staleVersion = $product->stock_version;

        app(CheckoutService::class)->complete($admin, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '600.00',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        $this->actingAs($admin);
        $this->put("/products/{$product->id}", [
            'name' => 'Caramel Latte',
            'price' => '175.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'stock_version' => $staleVersion,
            'is_active' => 1,
        ])->assertSessionHasErrors('stock_quantity');

        $product->refresh();
        $this->assertSame(7, $product->stock_quantity);

        $this->delete("/products/{$product->id}")->assertRedirect('/products');
        $product->refresh();
        $this->assertNotNull($product->archived_at);
        $this->assertFalse($product->is_active);

        $this->post("/products/{$product->id}/restore")->assertRedirect('/products');
        $product->refresh();
        $this->assertNull($product->archived_at);
        $this->assertTrue($product->is_active);
    }

    public function test_customer_crud_and_safe_deletion_for_unreferenced_vs_referenced(): void
    {
        $admin = $this->makeAdmin();
        $cashier = new User([
            'username' => 'cashier_cust',
            'full_name' => 'Cashier Cust',
            'password' => Hash::make('Cashier@123'),
        ]);
        $cashier->role = User::ROLE_CASHIER;
        $cashier->is_active = true;
        $cashier->save();

        $this->actingAs($cashier);
        $this->post('/customers', [
            'full_name' => 'Unreferenced Guest',
            'email' => 'unref@example.ph',
            'phone' => '+63 917 000 0001',
        ])->assertRedirect();

        $unref = Customer::where('email', 'unref@example.ph')->firstOrFail();
        $this->delete("/customers/{$unref->id}")->assertForbidden();

        $this->actingAs($admin);
        $this->delete("/customers/{$unref->id}")->assertRedirect('/customers');
        $this->assertDatabaseMissing('customers', ['id' => $unref->id]);

        $ref = Customer::create([
            'full_name' => 'Referenced Regular',
            'email' => 'regular@example.ph',
            'phone' => '+63 917 000 0002',
        ]);
        $product = Product::create(['name' => 'Brioche', 'price' => '95.00', 'stock_quantity' => 5, 'low_stock_threshold' => 2, 'is_active' => true]);
        app(CheckoutService::class)->complete($admin, [
            'checkout_token' => Str::uuid()->toString(),
            'customer_id' => $ref->id,
            'cash_received' => '100.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->delete("/customers/{$ref->id}")->assertRedirect('/customers');
        $this->assertSoftDeleted('customers', ['id' => $ref->id]);
    }

    public function test_staff_crud_password_hashing_and_final_admin_protection(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin('only_admin');
        $this->actingAs($admin);

        $this->put("/staff/{$admin->id}", [
            'username' => $admin->username,
            'full_name' => $admin->full_name,
            'role' => User::ROLE_CASHIER,
            'is_active' => 1,
        ])->assertSessionHasErrors('role');

        $this->delete("/staff/{$admin->id}")->assertSessionHasErrors('staff');

        $this->post('/staff', [
            'username' => 'newbarista',
            'full_name' => 'New Barista',
            'role' => User::ROLE_CASHIER,
            'is_active' => 1,
            'password' => 'StrongPass@1',
            'password_confirmation' => 'StrongPass@1',
            'avatar' => $this->makeRealJpegUpload('avatar.jpg'),
        ])->assertRedirect('/staff');

        $barista = User::where('username', 'newbarista')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPass@1', $barista->password));
        $this->assertNotNull($barista->avatar);
        Storage::disk('local')->assertExists('media/'.$barista->avatar);

        $this->put("/staff/{$barista->id}", [
            'username' => 'newbarista',
            'full_name' => 'Updated Barista',
            'role' => User::ROLE_CASHIER,
            'is_active' => 1,
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/staff');

        $barista->refresh();
        $this->assertSame('Updated Barista', $barista->full_name);
        $this->assertTrue(Hash::check('StrongPass@1', $barista->password));

        $this->delete("/staff/{$barista->id}")->assertRedirect('/staff');
        $this->assertDatabaseMissing('users', ['id' => $barista->id]);
    }

    public function test_creating_product_without_sku_automatically_generates_unique_sku(): void
    {
        $admin = $this->makeAdmin('admin_sku');
        $this->actingAs($admin);

        $category = Category::create([
            'name' => 'Espresso Bar',
            'slug' => 'espresso-bar',
            'sort_order' => 1,
            'is_drink' => true,
            'is_active' => true,
        ]);

        $this->post('/products', [
            'name' => 'Spanish Latte',
            'category_id' => $category->id,
            'sku' => '',
            'price' => '155.00',
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => 1,
        ])->assertRedirect();

        $first = Product::where('name', 'Spanish Latte')->firstOrFail();
        $this->assertNotNull($first->sku);
        $this->assertMatchesRegularExpression('/^ABC-[A-Z0-9]{3}-\d{4}$/', $first->sku);
        $this->assertStringStartsWith('ABC-ESP-', $first->sku);

        $this->post('/products', [
            'name' => 'Flat White',
            'category_id' => $category->id,
            'price' => '145.00',
            'stock_quantity' => 15,
            'low_stock_threshold' => 5,
            'is_active' => 1,
        ])->assertRedirect();

        $second = Product::where('name', 'Flat White')->firstOrFail();
        $this->assertNotNull($second->sku);
        $this->assertMatchesRegularExpression('/^ABC-[A-Z0-9]{3}-\d{4}$/', $second->sku);
        $this->assertNotSame($first->sku, $second->sku);

        $this->post('/products', [
            'name' => 'Single Origin Pour Over',
            'category_id' => $category->id,
            'sku' => 'CUSTOM-SKU-99',
            'price' => '180.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => 1,
        ])->assertRedirect();

        $third = Product::where('name', 'Single Origin Pour Over')->firstOrFail();
        $this->assertSame('CUSTOM-SKU-99', $third->sku);

        $indexResponse = $this->get('/products');
        $indexResponse->assertOk();
        $indexResponse->assertSee('data-product-image-placeholder', false);
        $indexResponse->assertDontSee('>ABC<', false);
        $indexResponse->assertDontSee('No Image');
    }
}
