<?php

namespace App\Http\Controllers;

use App\Services\Email\ContactEnquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ContactController extends Controller
{
    public function store(Request $request, ContactEnquiryService $enquiries): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect(route('home').'#contact')->with('contact_status', 'Thank you. Your message has been received.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $ipKey = 'contact-ip:'.$request->ip();
        $emailKey = 'contact-email:'.mb_strtolower(trim((string) $validated['email']));

        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($emailKey, 3)) {
            return redirect(route('home').'#contact')
                ->withInput()
                ->withErrors(['contact' => 'Too many enquiries sent recently. Please try again in a few minutes.'], 'contact');
        }

        RateLimiter::hit($ipKey, 300);
        RateLimiter::hit($emailKey, 300);

        $enquiry = $enquiries->submit($validated, $request->ip());

        return redirect(route('home').'#contact')->with(
            'contact_status',
            "Thank you, {$enquiry->name}. Your enquiry has been recorded under reference {$enquiry->reference_code}."
        );
    }
}
