<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Services\CheckoutService;
use App\Support\BusinessTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $search = trim((string) $request->query('search', ''));
        $from = $request->query('from');
        $to = $request->query('to');
        $staffId = $user->isAdmin() ? $request->query('staff_id') : null;

        $query = Sale::query()
            ->with(['order', 'product', 'customer', 'staff', 'saleAddons'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! $user->isAdmin()) {
            $query->where('sold_by', $user->id);
        } elseif ($staffId !== null && $staffId !== '' && ctype_digit((string) $staffId)) {
            $query->where('sold_by', (int) $staffId);
        }

        $validFrom = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;
        $validTo = is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null;

        if ($validFrom && $validTo && $validFrom <= $validTo) {
            [$utcStart, $utcEnd] = BusinessTime::utcBoundsForManilaRange($validFrom, $validTo);
            $query->whereBetween('created_at', [$utcStart, $utcEnd]);
        } elseif ($validFrom) {
            [$utcStart, ] = BusinessTime::utcBoundsForManilaRange($validFrom, $validFrom);
            $query->where('created_at', '>=', $utcStart);
        } elseif ($validTo) {
            [, $utcEnd] = BusinessTime::utcBoundsForManilaRange($validTo, $validTo);
            $query->where('created_at', '<=', $utcEnd);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('product_name_snapshot', 'like', $like)
                    ->orWhereHas('order', function ($oq) use ($like) {
                        $oq->where('order_number', 'like', $like)
                            ->orWhere('customer_name_snapshot', 'like', $like)
                            ->orWhere('staff_name_snapshot', 'like', $like);
                    });
            });
        }

        $sales = $query->paginate(20)->withQueryString();
        $staffList = $user->isAdmin()
            ? User::withTrashed()->orderBy('full_name')->get()
            : collect();

        return view('sales.index', [
            'sales' => $sales,
            'staffList' => $staffList,
            'filters' => [
                'search' => $search,
                'from' => $validFrom,
                'to' => $validTo,
                'staff_id' => $staffId,
            ],
        ]);
    }

    public function showOrder(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['sales.saleAddons', 'customer', 'staff']);

        return view('orders.show', ['order' => $order]);
    }

    public function receipt(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['sales.saleAddons', 'customer', 'staff']);

        return view('orders.receipt', ['order' => $order]);
    }

    public function voidOrder(Request $request, Order $order, CheckoutService $checkout): RedirectResponse|JsonResponse
    {
        $this->authorize('void', $order);

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        if ($request->user()->isAdmin()) {
            $approver = $request->user();
        } else {
            $throttleKey = 'void-override:'.$request->user()->id.'|'.$request->ip();

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                throw ValidationException::withMessages([
                    'admin_username' => 'Too many override attempts. Please wait a minute before trying again.',
                ]);
            }

            $override = $request->validate([
                'admin_username' => ['required', 'string'],
                'admin_password' => ['required', 'string'],
            ]);

            $candidate = User::query()
                ->where('username', trim((string) $override['admin_username']))
                ->first();

            if (
                ! $candidate
                || ! $candidate->isActiveAccount()
                || ! $candidate->isAdmin()
                || ! Hash::check((string) $override['admin_password'], (string) $candidate->password)
            ) {
                RateLimiter::hit($throttleKey, 60);

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Invalid or inactive admin/manager credentials.',
                        'errors' => [
                            'admin_username' => ['Invalid or inactive admin/manager credentials.'],
                        ],
                    ], 422);
                }

                return back()
                    ->withErrors(['admin_username' => 'Invalid or inactive admin/manager credentials.'])
                    ->withInput($request->except('admin_password'));
            }

            RateLimiter::clear($throttleKey);
            $approver = $candidate;
        }

        try {
            $voidedOrder = $checkout->voidOrder($order, $approver, $validated['void_reason']);
        } catch (CheckoutException $e) {
            $errorKey = $e->field ?? 'void_reason';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => [
                        $errorKey => [$e->getMessage()],
                    ],
                ], $e->statusCode);
            }

            return back()->withErrors([$errorKey => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'order' => $voidedOrder,
                'message' => 'Order #'.$order->order_number.' has been voided.',
            ]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order #'.$order->order_number.' has been voided.');
    }
}
