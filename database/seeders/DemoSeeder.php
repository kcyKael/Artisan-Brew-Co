<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleAddon;
use App\Models\User;
use App\Services\ImagePreparationService;
use App\Support\BusinessTime;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $images = app(ImagePreparationService::class);

        $staffRows = [
            ['username' => 'admin', 'full_name' => 'Elena Vance', 'role' => User::ROLE_ADMIN, 'password' => 'Admin@1234'],
            ['username' => 'manager', 'full_name' => 'Marco Reyes', 'role' => User::ROLE_ADMIN, 'password' => 'Manager@1234'],
            ['username' => 'cashier', 'full_name' => 'Sofia Mendoza', 'role' => User::ROLE_CASHIER, 'password' => 'Cashier@1234'],
            ['username' => 'barista', 'full_name' => 'Mateo Cruz', 'role' => User::ROLE_CASHIER, 'password' => 'Barista@1234'],
        ];

        $staffByUsername = [];
        foreach ($staffRows as $row) {
            $user = User::withTrashed()->where('username', $row['username'])->first();
            if (! $user) {
                $user = new User();
                $user->username = $row['username'];
                $user->full_name = $row['full_name'];
                $user->password = Hash::make($row['password']);
                $user->role = $row['role'];
                $user->is_active = true;
                $user->save();
            }
            $staffByUsername[$row['username']] = $user;
        }

        $customerRows = [
            ['full_name' => 'Maria Santos', 'email' => 'maria.santos@example.ph', 'phone' => '+63 917 555 0101', 'notes' => 'Prefers oat milk in Spanish Latte'],
            ['full_name' => 'Maria Clara Santos', 'email' => 'mclara.santos@example.ph', 'phone' => '+63 918 555 0102', 'notes' => 'Morning Cold Brew regular'],
            ['full_name' => 'Gabriel Lim', 'email' => 'gabriel.lim@example.ph', 'phone' => '+63 917 555 0103', 'notes' => 'Extra espresso shot'],
            ['full_name' => 'Camille Bautista', 'email' => 'camille.bautista@example.ph', 'phone' => '+63 919 555 0104', 'notes' => 'Matcha and croissant bundle'],
            ['full_name' => 'Rafael Aquino', 'email' => 'rafael.aquino@example.ph', 'phone' => '+63 920 555 0105', 'notes' => null],
            ['full_name' => 'Isabel Tan', 'email' => 'isabel.tan@example.ph', 'phone' => '+63 917 555 0106', 'notes' => 'Less ice on iced drinks'],
            ['full_name' => 'Lorenzo Navarro', 'email' => 'lorenzo.navarro@example.ph', 'phone' => '+63 927 555 0107', 'notes' => null],
            ['full_name' => 'Beatriz Villanueva', 'email' => 'beatriz.v@example.ph', 'phone' => '+63 917 555 0108', 'notes' => 'Pastry bulk orders on Fridays'],
        ];

        $customers = [];
        foreach ($customerRows as $row) {
            $customer = Customer::withTrashed()->firstOrCreate(
                ['email' => $row['email']],
                [
                    'full_name' => $row['full_name'],
                    'phone' => $row['phone'],
                    'notes' => $row['notes'],
                ]
            );
            $customers[] = $customer;
        }

        $categoryDefs = [
            ['name' => 'Espresso Bar', 'slug' => 'espresso-bar', 'sort_order' => 1, 'is_drink' => true],
            ['name' => 'Signature Lattes', 'slug' => 'signature-lattes', 'sort_order' => 2, 'is_drink' => true],
            ['name' => 'Matcha & Tea', 'slug' => 'matcha-tea', 'sort_order' => 3, 'is_drink' => true],
            ['name' => 'Cold Brew & Chocolate', 'slug' => 'cold-brew-chocolate', 'sort_order' => 4, 'is_drink' => true],
            ['name' => 'Artisan Pastries', 'slug' => 'artisan-pastries', 'sort_order' => 5, 'is_drink' => false],
        ];

        $categories = [];
        foreach ($categoryDefs as $def) {
            $categories[$def['slug']] = Category::firstOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'sort_order' => $def['sort_order'],
                    'is_drink' => $def['is_drink'],
                    'is_active' => true,
                ]
            );
        }

        $addonDefs = [
            ['name' => 'Extra Espresso Shot', 'price' => '35.00', 'sort_order' => 1],
            ['name' => 'Oat Milk', 'price' => '40.00', 'sort_order' => 2],
            ['name' => 'Almond Milk', 'price' => '40.00', 'sort_order' => 3],
            ['name' => 'Vanilla Syrup', 'price' => '25.00', 'sort_order' => 4],
            ['name' => 'Caramel Drizzle', 'price' => '25.00', 'sort_order' => 5],
            ['name' => 'Whipped Cream', 'price' => '30.00', 'sort_order' => 6],
        ];

        $addons = [];
        foreach ($addonDefs as $def) {
            $addons[$def['name']] = Addon::firstOrCreate(
                ['name' => $def['name']],
                [
                    'price' => $def['price'],
                    'sort_order' => $def['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        $giftCardDefs = [
            ['code' => 'ABC-GIFT-500', 'balance' => '500.00'],
            ['code' => 'ABC-GIFT-1000', 'balance' => '1000.00'],
            ['code' => 'ABC-GIFT-250', 'balance' => '250.00'],
        ];

        foreach ($giftCardDefs as $cardDef) {
            GiftCard::firstOrCreate(
                ['code' => $cardDef['code']],
                [
                    'initial_balance' => $cardDef['balance'],
                    'remaining_balance' => $cardDef['balance'],
                    'is_active' => true,
                    'expires_at' => null,
                ]
            );
        }

        $seedImagesDir = resource_path('assets/seed-images');
        $productDefs = [
            ['sku' => 'ABC-ESP-01', 'name' => 'Americano', 'cat' => 'espresso-bar', 'price' => '130.00', 'stock' => 42, 'low' => 8, 'img' => 'espresso-latte.jpg', 'mode' => 'hot_iced', 'addons' => ['Extra Espresso Shot', 'Vanilla Syrup']],
            ['sku' => 'ABC-ESP-02', 'name' => 'Cafe Latte', 'cat' => 'espresso-bar', 'price' => '155.00', 'stock' => 36, 'low' => 8, 'img' => 'espresso-latte.jpg', 'mode' => 'hot_iced', 'addons' => ['Extra Espresso Shot', 'Oat Milk', 'Almond Milk', 'Vanilla Syrup', 'Caramel Drizzle']],
            ['sku' => 'ABC-ESP-03', 'name' => 'Cappuccino', 'cat' => 'espresso-bar', 'price' => '155.00', 'stock' => 28, 'low' => 6, 'img' => 'espresso-latte.jpg', 'mode' => 'hot_only', 'addons' => ['Extra Espresso Shot', 'Oat Milk', 'Almond Milk', 'Vanilla Syrup']],
            ['sku' => 'ABC-ESP-04', 'name' => 'Flat White', 'cat' => 'espresso-bar', 'price' => '160.00', 'stock' => 4, 'low' => 5, 'img' => 'espresso-latte.jpg', 'mode' => 'hot_only', 'addons' => ['Extra Espresso Shot', 'Oat Milk', 'Almond Milk']],
            ['sku' => 'ABC-ESP-05', 'name' => 'Single-Origin Cortado', 'cat' => 'espresso-bar', 'price' => '145.00', 'stock' => 18, 'low' => 5, 'img' => 'espresso-latte.jpg', 'mode' => 'hot_only', 'addons' => ['Extra Espresso Shot', 'Oat Milk']],

            ['sku' => 'ABC-SIG-01', 'name' => 'Spanish Latte', 'cat' => 'signature-lattes', 'price' => '175.00', 'stock' => 34, 'low' => 8, 'img' => 'iced-coffee.jpg', 'mode' => 'hot_iced', 'addons' => ['Extra Espresso Shot', 'Oat Milk', 'Caramel Drizzle']],
            ['sku' => 'ABC-SIG-02', 'name' => 'Caramel Macchiato', 'cat' => 'signature-lattes', 'price' => '180.00', 'stock' => 26, 'low' => 6, 'img' => 'iced-coffee.jpg', 'mode' => 'hot_iced', 'addons' => ['Extra Espresso Shot', 'Oat Milk', 'Almond Milk', 'Caramel Drizzle', 'Whipped Cream']],
            ['sku' => 'ABC-SIG-03', 'name' => 'Mocha', 'cat' => 'signature-lattes', 'price' => '175.00', 'stock' => 22, 'low' => 6, 'img' => 'dark-chocolate.jpg', 'mode' => 'hot_iced', 'addons' => ['Extra Espresso Shot', 'Oat Milk', 'Whipped Cream']],
            ['sku' => 'ABC-SIG-04', 'name' => 'Salted Honey Oat Latte', 'cat' => 'signature-lattes', 'price' => '190.00', 'stock' => 3, 'low' => 5, 'img' => 'iced-coffee.jpg', 'mode' => 'hot_iced', 'addons' => ['Extra Espresso Shot', 'Caramel Drizzle']],

            ['sku' => 'ABC-TEA-01', 'name' => 'Matcha Latte', 'cat' => 'matcha-tea', 'price' => '185.00', 'stock' => 30, 'low' => 6, 'img' => 'matcha-latte.jpg', 'mode' => 'hot_iced', 'addons' => ['Oat Milk', 'Almond Milk', 'Vanilla Syrup', 'Whipped Cream']],
            ['sku' => 'ABC-TEA-02', 'name' => 'Dirty Matcha', 'cat' => 'matcha-tea', 'price' => '205.00', 'stock' => 19, 'low' => 5, 'img' => 'matcha-latte.jpg', 'mode' => 'hot_iced', 'addons' => ['Extra Espresso Shot', 'Oat Milk', 'Almond Milk', 'Vanilla Syrup']],
            ['sku' => 'ABC-TEA-03', 'name' => 'Roasted Hojicha Latte', 'cat' => 'matcha-tea', 'price' => '180.00', 'stock' => 0, 'low' => 5, 'img' => 'matcha-latte.jpg', 'mode' => 'hot_iced', 'addons' => ['Oat Milk', 'Vanilla Syrup']],

            ['sku' => 'ABC-CLD-01', 'name' => 'Cold Brew', 'cat' => 'cold-brew-chocolate', 'price' => '160.00', 'stock' => 25, 'low' => 6, 'img' => 'iced-coffee.jpg', 'mode' => 'iced_only', 'addons' => ['Extra Espresso Shot', 'Oat Milk', 'Vanilla Syrup', 'Caramel Drizzle']],
            ['sku' => 'ABC-CLD-02', 'name' => 'Vanilla Sweet Cream Cold Brew', 'cat' => 'cold-brew-chocolate', 'price' => '185.00', 'stock' => 20, 'low' => 5, 'img' => 'iced-coffee.jpg', 'mode' => 'iced_only', 'addons' => ['Extra Espresso Shot', 'Caramel Drizzle']],
            ['sku' => 'ABC-CLD-03', 'name' => 'Chocolate', 'cat' => 'cold-brew-chocolate', 'price' => '165.00', 'stock' => 24, 'low' => 5, 'img' => 'dark-chocolate.jpg', 'mode' => 'hot_iced', 'addons' => ['Oat Milk', 'Almond Milk', 'Whipped Cream', 'Caramel Drizzle']],

            ['sku' => 'ABC-PAS-01', 'name' => 'Butter Croissant', 'cat' => 'artisan-pastries', 'price' => '125.00', 'stock' => 28, 'low' => 6, 'img' => 'croissant-pastry.jpg', 'mode' => 'pastry', 'addons' => []],
            ['sku' => 'ABC-PAS-02', 'name' => 'Pain au Chocolat', 'cat' => 'artisan-pastries', 'price' => '145.00', 'stock' => 16, 'low' => 5, 'img' => 'croissant-pastry.jpg', 'mode' => 'pastry', 'addons' => []],
            ['sku' => 'ABC-PAS-03', 'name' => 'Almond Croissant', 'cat' => 'artisan-pastries', 'price' => '160.00', 'stock' => 3, 'low' => 5, 'img' => 'croissant-pastry.jpg', 'mode' => 'pastry', 'addons' => []],
            ['sku' => 'ABC-PAS-04', 'name' => 'Sea Salt Dark Chocolate Cookie', 'cat' => 'artisan-pastries', 'price' => '115.00', 'stock' => 32, 'low' => 6, 'img' => 'cookie-bakery.jpg', 'mode' => 'pastry', 'addons' => []],
            ['sku' => 'ABC-PAS-05', 'name' => 'Double Espresso Brownie', 'cat' => 'artisan-pastries', 'price' => '135.00', 'stock' => 0, 'low' => 5, 'img' => 'cookie-bakery.jpg', 'mode' => 'pastry', 'addons' => []],
        ];

        $productsBySku = [];
        foreach ($productDefs as $def) {
            $product = Product::where('sku', $def['sku'])->first();
            if (! $product) {
                $imagePath = null;
                $sourceImg = $seedImagesDir.DIRECTORY_SEPARATOR.$def['img'];
                if (is_file($sourceImg)) {
                    $imagePath = $images->prepareFromLocalFile($sourceImg, 'products');
                }

                $product = new Product([
                    'name' => $def['name'],
                    'category_id' => $categories[$def['cat']]->id,
                    'sku' => $def['sku'],
                    'description' => 'Crafted daily at Artisan Brew Co. using specialty single-origin beans and small-batch ingredients.',
                    'price' => $def['price'],
                    'stock_quantity' => $def['stock'],
                    'low_stock_threshold' => $def['low'],
                    'image' => $imagePath,
                    'is_active' => true,
                ]);
                $product->save();
            }

            if ($def['mode'] !== 'pastry' && $product->variants()->count() === 0) {
                $variantTemplates = match ($def['mode']) {
                    'hot_iced' => [
                        ['size_label' => '12 oz (Regular)', 'temperature' => 'hot', 'price_adjustment' => '0.00', 'sort_order' => 1],
                        ['size_label' => '16 oz (Large)', 'temperature' => 'hot', 'price_adjustment' => '25.00', 'sort_order' => 2],
                        ['size_label' => '12 oz (Regular)', 'temperature' => 'iced', 'price_adjustment' => '15.00', 'sort_order' => 3],
                        ['size_label' => '16 oz (Large)', 'temperature' => 'iced', 'price_adjustment' => '40.00', 'sort_order' => 4],
                    ],
                    'hot_only' => [
                        ['size_label' => '8 oz (Classic)', 'temperature' => 'hot', 'price_adjustment' => '0.00', 'sort_order' => 1],
                        ['size_label' => '12 oz (Large)', 'temperature' => 'hot', 'price_adjustment' => '25.00', 'sort_order' => 2],
                    ],
                    'iced_only' => [
                        ['size_label' => '12 oz (Regular)', 'temperature' => 'iced', 'price_adjustment' => '0.00', 'sort_order' => 1],
                        ['size_label' => '16 oz (Large)', 'temperature' => 'iced', 'price_adjustment' => '30.00', 'sort_order' => 2],
                    ],
                    default => [],
                };

                foreach ($variantTemplates as $vt) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'size_label' => $vt['size_label'],
                        'temperature' => $vt['temperature'],
                        'price_adjustment' => $vt['price_adjustment'],
                        'is_active' => true,
                        'sort_order' => $vt['sort_order'],
                    ]);
                }
            }

            if ($def['addons'] !== [] && $product->addons()->count() === 0) {
                $addonIds = array_map(fn (string $name) => $addons[$name]->id, $def['addons']);
                $product->addons()->sync($addonIds);
            }

            $productsBySku[$def['sku']] = $product->fresh(['variants', 'addons']);
        }

        if (Order::count() === 0) {
            $nowManila = BusinessTime::nowManila();

            $historicalOrders = [
                ['days_ago' => 13, 'hour' => 9, 'staff' => 'cashier', 'customer' => 0, 'lines' => [['sku' => 'ABC-ESP-01', 'variant' => 0, 'addons' => [], 'qty' => 2], ['sku' => 'ABC-PAS-01', 'variant' => null, 'addons' => [], 'qty' => 2]]],
                ['days_ago' => 12, 'hour' => 10, 'staff' => 'barista', 'customer' => null, 'lines' => [['sku' => 'ABC-SIG-01', 'variant' => 2, 'addons' => ['Oat Milk'], 'qty' => 1], ['sku' => 'ABC-PAS-04', 'variant' => null, 'addons' => [], 'qty' => 2]]],
                ['days_ago' => 11, 'hour' => 14, 'staff' => 'cashier', 'customer' => 2, 'lines' => [['sku' => 'ABC-ESP-02', 'variant' => 3, 'addons' => ['Extra Espresso Shot'], 'qty' => 2]]],
                ['days_ago' => 10, 'hour' => 11, 'staff' => 'admin', 'customer' => 3, 'lines' => [['sku' => 'ABC-TEA-01', 'variant' => 3, 'addons' => ['Oat Milk'], 'qty' => 2], ['sku' => 'ABC-PAS-01', 'variant' => null, 'addons' => [], 'qty' => 1]]],
                ['days_ago' => 9, 'hour' => 15, 'staff' => 'barista', 'customer' => null, 'lines' => [['sku' => 'ABC-CLD-01', 'variant' => 1, 'addons' => ['Vanilla Syrup'], 'qty' => 3]]],
                ['days_ago' => 8, 'hour' => 8, 'staff' => 'cashier', 'customer' => 1, 'lines' => [['sku' => 'ABC-CLD-02', 'variant' => 0, 'addons' => [], 'qty' => 1], ['sku' => 'ABC-PAS-02', 'variant' => null, 'addons' => [], 'qty' => 1]]],
                ['days_ago' => 7, 'hour' => 16, 'staff' => 'cashier', 'customer' => 4, 'lines' => [['sku' => 'ABC-SIG-02', 'variant' => 3, 'addons' => ['Caramel Drizzle', 'Whipped Cream'], 'qty' => 2]]],
                ['days_ago' => 6, 'hour' => 10, 'staff' => 'barista', 'customer' => null, 'lines' => [['sku' => 'ABC-ESP-03', 'variant' => 1, 'addons' => [], 'qty' => 2], ['sku' => 'ABC-PAS-04', 'variant' => null, 'addons' => [], 'qty' => 3]]],
                ['days_ago' => 5, 'hour' => 13, 'staff' => 'cashier', 'customer' => 5, 'lines' => [['sku' => 'ABC-TEA-02', 'variant' => 2, 'addons' => ['Oat Milk'], 'qty' => 2]]],
                ['days_ago' => 4, 'hour' => 17, 'staff' => 'barista', 'customer' => 6, 'lines' => [['sku' => 'ABC-CLD-03', 'variant' => 0, 'addons' => ['Whipped Cream'], 'qty' => 2], ['sku' => 'ABC-PAS-01', 'variant' => null, 'addons' => [], 'qty' => 2]]],
                ['days_ago' => 3, 'hour' => 9, 'staff' => 'cashier', 'customer' => 7, 'lines' => [['sku' => 'ABC-PAS-01', 'variant' => null, 'addons' => [], 'qty' => 4], ['sku' => 'ABC-PAS-02', 'variant' => null, 'addons' => [], 'qty' => 2], ['sku' => 'ABC-ESP-02', 'variant' => 1, 'addons' => [], 'qty' => 2]]],
                ['days_ago' => 2, 'hour' => 11, 'staff' => 'barista', 'customer' => 0, 'lines' => [['sku' => 'ABC-SIG-01', 'variant' => 3, 'addons' => ['Oat Milk', 'Extra Espresso Shot'], 'qty' => 2]]],
                ['days_ago' => 2, 'hour' => 16, 'staff' => 'cashier', 'customer' => null, 'lines' => [['sku' => 'ABC-ESP-05', 'variant' => 0, 'addons' => [], 'qty' => 2], ['sku' => 'ABC-PAS-04', 'variant' => null, 'addons' => [], 'qty' => 1]]],
                ['days_ago' => 1, 'hour' => 10, 'staff' => 'cashier', 'customer' => 2, 'lines' => [['sku' => 'ABC-SIG-03', 'variant' => 2, 'addons' => ['Extra Espresso Shot'], 'qty' => 2], ['sku' => 'ABC-CLD-01', 'variant' => 1, 'addons' => [], 'qty' => 1]]],
                ['days_ago' => 1, 'hour' => 15, 'staff' => 'barista', 'customer' => null, 'lines' => [['sku' => 'ABC-TEA-01', 'variant' => 2, 'addons' => ['Oat Milk'], 'qty' => 2]]],
                ['days_ago' => 0, 'hour' => 0, 'staff' => 'cashier', 'customer' => 0, 'lines' => [['sku' => 'ABC-SIG-01', 'variant' => 2, 'addons' => ['Oat Milk'], 'qty' => 2], ['sku' => 'ABC-PAS-01', 'variant' => null, 'addons' => [], 'qty' => 2]]],
                ['days_ago' => 0, 'hour' => 0, 'staff' => 'barista', 'customer' => 3, 'lines' => [['sku' => 'ABC-TEA-01', 'variant' => 3, 'addons' => [], 'qty' => 1], ['sku' => 'ABC-ESP-02', 'variant' => 0, 'addons' => ['Vanilla Syrup'], 'qty' => 1]]],
                ['days_ago' => 0, 'hour' => 0, 'staff' => 'cashier', 'customer' => null, 'lines' => [['sku' => 'ABC-CLD-02', 'variant' => 1, 'addons' => ['Caramel Drizzle'], 'qty' => 2]]],
            ];

            foreach ($historicalOrders as $idx => $spec) {
                $staff = $staffByUsername[$spec['staff']];
                $customer = $spec['customer'] !== null ? $customers[$spec['customer']] : null;

                $manilaTs = $spec['days_ago'] === 0
                    ? $nowManila->subMinutes(45 - ($idx % 10) * 3)
                    : $nowManila->subDays($spec['days_ago'])->setTime($spec['hour'], 15 + ($idx * 3) % 40, 0);

                $utcTs = $manilaTs->setTimezone('UTC');
                $totalCentavos = 0;
                $preparedLines = [];

                foreach ($spec['lines'] as $lineSpec) {
                    $product = $productsBySku[$lineSpec['sku']];
                    $variant = $lineSpec['variant'] !== null ? $product->variants->values()->get($lineSpec['variant']) : null;
                    $lineAddons = collect($lineSpec['addons'])->map(fn (string $name) => $addons[$name]);

                    $baseC = Money::toCentavos((string) $product->price);
                    $varC = $variant ? Money::toCentavos((string) $variant->price_adjustment) : 0;
                    $addonC = $lineAddons->sum(fn (Addon $a) => Money::toCentavos((string) $a->price));
                    $unitC = $baseC + $varC + $addonC;
                    $lineC = $unitC * $lineSpec['qty'];
                    $totalCentavos += $lineC;

                    $preparedLines[] = compact('product', 'variant', 'lineAddons', 'lineSpec', 'baseC', 'varC', 'addonC', 'unitC', 'lineC');
                }

                $cashCentavos = (int) (ceil($totalCentavos / 10000) * 10000);
                if ($cashCentavos < $totalCentavos) {
                    $cashCentavos = $totalCentavos;
                }
                $changeCentavos = $cashCentavos - $totalCentavos;

                $order = new Order();
                $order->order_number = 'TMP-'.Str::upper(Str::random(12));
                $order->customer_id = $customer?->id;
                $order->sold_by = $staff->id;
                $order->customer_name_snapshot = $customer?->full_name ?? 'Walk-in customer';
                $order->staff_name_snapshot = $staff->full_name;
                $order->source = $idx % 5 === 0 ? 'record_sale' : 'pos';
                $order->subtotal_amount = Money::fromCentavos($totalCentavos);
                $order->total_amount = Money::fromCentavos($totalCentavos);
                $order->cash_received = Money::fromCentavos($cashCentavos);
                $order->change_amount = Money::fromCentavos($changeCentavos);
                $order->checkout_token = 'demo-seed-'.str_pad((string) ($idx + 1), 12, '0', STR_PAD_LEFT);
                $order->request_hash = hash('sha256', 'demo-order-'.$idx);
                $order->created_at = $utcTs;
                $order->updated_at = $utcTs;
                $order->save();

                $order->order_number = sprintf('ABC-%s-%06d', $manilaTs->format('Ymd'), $order->id);
                $order->save();

                foreach ($preparedLines as $line) {
                    $sale = new Sale();
                    $sale->order_id = $order->id;
                    $sale->product_id = $line['product']->id;
                    $sale->product_variant_id = $line['variant']?->id;
                    $sale->customer_id = $customer?->id;
                    $sale->sold_by = $staff->id;
                    $sale->product_name_snapshot = $line['product']->name;
                    $sale->variant_label_snapshot = $line['variant']?->displayLabel();
                    $sale->base_price_snapshot = Money::fromCentavos($line['baseC']);
                    $sale->variant_adjustment_snapshot = Money::fromCentavos($line['varC']);
                    $sale->addons_total_snapshot = Money::fromCentavos($line['addonC']);
                    $sale->unit_price = Money::fromCentavos($line['unitC']);
                    $sale->quantity = $line['lineSpec']['qty'];
                    $sale->total_price = Money::fromCentavos($line['lineC']);
                    $sale->created_at = $utcTs;
                    $sale->updated_at = $utcTs;
                    $sale->save();

                    foreach ($line['lineAddons'] as $addon) {
                        SaleAddon::create([
                            'sale_id' => $sale->id,
                            'addon_id' => $addon->id,
                            'addon_name_snapshot' => $addon->name,
                            'addon_price_snapshot' => $addon->price,
                            'created_at' => $utcTs,
                        ]);
                    }
                }
            }
        }
    }
}
