<?php

use App\Http\Controllers\Admin\EmailCenterController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MenuConfigController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptEmailController;
use App\Http\Controllers\RecordSaleController;
use App\Http\Controllers\SalesHistoryController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\Webhook\BrevoWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/menu', [LandingController::class, 'menu'])->name('menu.public');
Route::get('/about', [LandingController::class, 'about'])->name('about');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
Route::match(['get', 'post'], '/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::get('/newsletter/preferences/{token}', [NewsletterController::class, 'preferences'])->name('newsletter.preferences');

Route::get('/receipts/signed/{order}', [ReceiptEmailController::class, 'showSigned'])
    ->middleware('signed')
    ->name('receipts.signed');

Route::post('/webhooks/brevo/transactional', [BrevoWebhookController::class, 'transactional'])->name('webhooks.brevo.transactional');
Route::post('/webhooks/brevo/marketing', [BrevoWebhookController::class, 'marketing'])->name('webhooks.brevo.marketing');

Route::get('/media/{type}/{filename}', [MediaController::class, 'show'])
    ->whereIn('type', ['products', 'avatars'])
    ->where('filename', '[a-zA-Z0-9_-]+\.(jpg|webp)')
    ->name('media.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
    Route::get('/invitations/{token}', [StaffInvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [StaffInvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::get('/pos/customers/search', [PosController::class, 'searchCustomers'])->name('pos.customers.search');
    Route::get('/pos/gift-cards/lookup', [PosController::class, 'lookupGiftCard'])->name('pos.gift-cards.lookup');

    Route::get('/sales/record', [RecordSaleController::class, 'create'])->name('sales.record');
    Route::post('/sales/record', [RecordSaleController::class, 'store'])->name('sales.record.store');

    Route::get('/sales', [SalesHistoryController::class, 'index'])->name('sales.index');
    Route::get('/orders/{order}', [SalesHistoryController::class, 'showOrder'])->name('orders.show');
    Route::get('/orders/{order}/receipt', [SalesHistoryController::class, 'receipt'])->name('orders.receipt');
    Route::post('/orders/{order}/void', [SalesHistoryController::class, 'voidOrder'])->name('orders.void');
    Route::post('/orders/{order}/email-receipt', [ReceiptEmailController::class, 'send'])->name('orders.email-receipt');

    Route::resource('customers', CustomerController::class);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/verify-email/{token}', [EmailVerificationController::class, 'verify'])->name('profile.verify-email');

    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');

        Route::resource('products', ProductController::class)->except(['show']);
        Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
        Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');

        Route::resource('staff', StaffController::class)->except(['show']);
        Route::post('/staff/{staff}/resend-invitation', [StaffInvitationController::class, 'resend'])->name('staff.invitations.resend');
        Route::post('/staff/{staff}/revoke-invitation', [StaffInvitationController::class, 'revoke'])->name('staff.invitations.revoke');

        Route::prefix('admin/email')->name('admin.email.')->group(function () {
            Route::get('/', [EmailCenterController::class, 'settings'])->name('settings');
            Route::put('/settings', [EmailCenterController::class, 'updateSettings'])->name('settings.update');
            Route::post('/test', [EmailCenterController::class, 'sendTest'])->name('test');
            Route::get('/templates/{template}/preview', [EmailCenterController::class, 'previewTemplate'])->name('templates.preview');
            Route::post('/daily-summary', [EmailCenterController::class, 'triggerDailySummary'])->name('daily-summary');
            Route::post('/stock-digest', [EmailCenterController::class, 'triggerStockDigest'])->name('stock-digest');

            Route::get('/deliveries', [EmailCenterController::class, 'deliveries'])->name('deliveries');
            Route::post('/deliveries/{delivery}/retry', [EmailCenterController::class, 'retryDelivery'])->name('deliveries.retry');
            Route::post('/deliveries/recover', [EmailCenterController::class, 'recoverOutbox'])->name('deliveries.recover');

            Route::get('/enquiries', [EmailCenterController::class, 'enquiries'])->name('enquiries');
            Route::patch('/enquiries/{enquiry}', [EmailCenterController::class, 'updateEnquiry'])->name('enquiries.update');

            Route::get('/subscribers', [EmailCenterController::class, 'subscribers'])->name('subscribers');
            Route::post('/subscribers/{subscriber}/unsubscribe', [EmailCenterController::class, 'unsubscribeSubscriber'])->name('subscribers.unsubscribe');

            Route::get('/campaigns', [EmailCenterController::class, 'campaigns'])->name('campaigns');
            Route::post('/campaigns', [EmailCenterController::class, 'storeCampaign'])->name('campaigns.store');
            Route::get('/campaigns/{campaign}/preview', [EmailCenterController::class, 'previewCampaign'])->name('campaigns.preview');
            Route::post('/campaigns/{campaign}/send-test', [EmailCenterController::class, 'sendCampaignTest'])->name('campaigns.send-test');
            Route::post('/campaigns/{campaign}/send', [EmailCenterController::class, 'sendCampaign'])->name('campaigns.send');
            Route::post('/campaigns/{campaign}/sync', [EmailCenterController::class, 'syncCampaignStats'])->name('campaigns.sync');
        });

        Route::get('/menu-config', [MenuConfigController::class, 'index'])->name('menu-config.index');
        Route::post('/menu-config/categories', [MenuConfigController::class, 'storeCategory'])->name('menu-config.categories.store');
        Route::put('/menu-config/categories/{category}', [MenuConfigController::class, 'updateCategory'])->name('menu-config.categories.update');
        Route::delete('/menu-config/categories/{category}', [MenuConfigController::class, 'destroyCategory'])->name('menu-config.categories.destroy');

        Route::post('/menu-config/addons', [MenuConfigController::class, 'storeAddon'])->name('menu-config.addons.store');
        Route::put('/menu-config/addons/{addon}', [MenuConfigController::class, 'updateAddon'])->name('menu-config.addons.update');
        Route::delete('/menu-config/addons/{addon}', [MenuConfigController::class, 'destroyAddon'])->name('menu-config.addons.destroy');

        Route::post('/menu-config/products/{product}/variants', [MenuConfigController::class, 'storeVariant'])->name('menu-config.variants.store');
        Route::put('/menu-config/variants/{variant}', [MenuConfigController::class, 'updateVariant'])->name('menu-config.variants.update');
        Route::delete('/menu-config/variants/{variant}', [MenuConfigController::class, 'destroyVariant'])->name('menu-config.variants.destroy');

        Route::put('/menu-config/products/{product}/addons', [MenuConfigController::class, 'syncProductAddons'])->name('menu-config.products.addons.sync');
    });
});
