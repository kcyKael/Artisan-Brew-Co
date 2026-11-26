<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
            ->withCount('orders')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('full_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', "Customer '{$customer->full_name}' added.");
    }

    public function show(Customer $customer): View
    {
        $orders = $customer->orders()
            ->with('staff')
            ->withCount('sales')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('customers.show', [
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', "Customer '{$customer->full_name}' updated.");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $name = $customer->full_name;
        if ($customer->orders()->exists() || $customer->sales()->exists()) {
            $customer->delete();

            return redirect()
                ->route('customers.index')
                ->with('status', "Customer '{$name}' removed from active records while preserving historical receipts.");
        }

        $customer->forceDelete();

        return redirect()
            ->route('customers.index')
            ->with('status', "Customer '{$name}' deleted permanently.");
    }
}
