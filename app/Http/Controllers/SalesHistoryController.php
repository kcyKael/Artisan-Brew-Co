<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Http\Request;
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
}
