<?php

namespace App\Http\Controllers;

use App\Models\MarketingSubscriber;
use App\Services\Email\MarketingSubscriberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    public function subscribe(Request $request, MarketingSubscriberService $subscribers): RedirectResponse
    {
        if (filled($request->input('company'))) {
            return redirect(route('home').'#newsletter')->with(
                'newsletter_status',
                'Check your email inbox for a confirmation link.'
            );
        }

        $validated = $request->validate([
            'newsletter_email' => ['required', 'string', 'email', 'max:191'],
            'newsletter_name' => ['nullable', 'string', 'max:120'],
            'newsletter_consent' => ['accepted'],
        ], [
            'newsletter_consent.accepted' => 'Please confirm that you wish to receive promotional updates.',
        ]);

        $email = mb_strtolower(trim((string) $validated['newsletter_email']));
        $throttleKey = 'newsletter:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 4)) {
            return redirect(route('home').'#newsletter')
                ->withErrors(['newsletter_email' => 'Too many subscription requests. Please wait a few minutes.'], 'newsletter');
        }

        RateLimiter::hit($throttleKey, 300);

        $sub = $subscribers->requestSubscription(
            $email,
            $validated['newsletter_name'] ?? null,
            'landing_newsletter_form',
        );

        $message = $sub->isEligibleForCampaigns()
            ? 'This email address is already confirmed on our subscriber list.'
            : ($sub->status === MarketingSubscriber::STATUS_SUPPRESSED
                ? 'This email address is suppressed due to a prior bounce or block and cannot be enrolled automatically.'
                : 'Almost there—please confirm your subscription via the link sent to your email.');

        return redirect(route('home').'#newsletter')->with('newsletter_status', $message);
    }

    public function confirm(string $token, MarketingSubscriberService $subscribers): View
    {
        $subscriber = $subscribers->confirmByToken($token);

        return view('landing.newsletter-status', [
            'mode' => 'confirm',
            'subscriber' => $subscriber,
            'success' => $subscriber !== null && $subscriber->isEligibleForCampaigns(),
        ]);
    }

    public function unsubscribe(Request $request, string $token, MarketingSubscriberService $subscribers): View
    {
        $subscriber = $subscribers->unsubscribeByToken($token);

        return view('landing.newsletter-status', [
            'mode' => 'unsubscribe',
            'subscriber' => $subscriber,
            'success' => $subscriber !== null,
        ]);
    }

    public function preferences(string $token, MarketingSubscriberService $subscribers): View
    {
        $subscriber = $subscribers->findByUnsubscribeToken($token);

        return view('landing.newsletter-status', [
            'mode' => 'preferences',
            'token' => $token,
            'subscriber' => $subscriber,
            'success' => $subscriber !== null,
        ]);
    }
}
