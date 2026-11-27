<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Services\Email\MarketingSubscriberService;
use App\Services\Email\OrderReceiptEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ReceiptEmailController extends Controller
{
    public function send(
        Request $request,
        Order $order,
        OrderReceiptEmailService $receiptEmails,
        MarketingSubscriberService $subscribers,
    ): RedirectResponse|JsonResponse {
        $this->authorize('view', $order);

        if ($order->status !== 'completed') {
            return $request->wantsJson()
                ? response()->json(['message' => 'Digital receipts can only be sent for completed orders.'], 422)
                : back()->withErrors(['receipt_email' => 'Digital receipts can only be sent for completed orders.']);
        }

        $throttleKey = 'email-receipt:'.$order->id.'|'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors(['receipt_email' => 'Receipt resend limit reached. Please wait a minute before trying again.']);
        }

        $validated = $request->validate([
            'receipt_email' => ['required', 'string', 'email', 'max:191'],
            'confirm_address' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ], [
            'confirm_address.accepted' => 'Please confirm the recipient email address before sending.',
        ]);

        RateLimiter::hit($throttleKey, 60);

        $email = mb_strtolower(trim((string) $validated['receipt_email']));
        $delivery = $receiptEmails->queueResendReceipt($order, $email, $request->user());

        if (! $delivery) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Customer digital receipts are currently disabled in Email Settings.'], 422)
                : back()->withErrors(['receipt_email' => 'Customer digital receipts are currently disabled in Email Settings.']);
        }

        if ($request->boolean('marketing_opt_in')) {
            $customer = $order->customer_id ? Customer::query()->find($order->customer_id) : null;
            $subscribers->requestSubscription(
                $email,
                $order->customer_name_snapshot ?: $customer?->full_name,
                'order_receipt_opt_in',
                $customer
            );
        }

        $statusNote = $delivery->status === 'simulated'
            ? "Receipt queued/recorded in preview mode for {$email}."
            : "Receipt email queued for {$email}.";

        return $request->wantsJson()
            ? response()->json(['message' => $statusNote, 'delivery_status' => $delivery->status])
            : back()->with('status', $statusNote);
    }

    public function showSigned(Order $order): View
    {
        abort_unless($order->status === 'completed', 404);
        $order->load(['sales.saleAddons']);

        return view('orders.signed-receipt', [
            'order' => $order,
        ]);
    }
}
