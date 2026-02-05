@component('mail::message')
# Subscription Activated! 🚀

Hello {{ $subscription->business->owner->name }},

Your payment was successful and your subscription has been activated.

## Plan Details:

- **Plan:** {{ $subscription->plan_name }}
- **Billing Cycle:** {{ $billingLabel }}
- **Amount:** {{ $subscription->currency }} {{ number_format($subscription->amount, 2) }}
- **Receipt/Transaction:** {{ $subscription->mpesa_receipt ?? $subscription->transaction_id }}
- **Starts:** {{ $subscription->starts_at->format('F j, Y') }}
- **Expires:** {{ $subscription->ends_at->format('F j, Y') }} ({{ $billingLabel }} renewal)

@component('mail::button', ['url' => url('/dashboard')])
Launch Workplace
@endcomponent

Your retail workspace is now fully operational with all premium features unlocked.

A payment receipt has been attached for your records.

Thanks,<br>
{{ config('app.company_name', 'Doitix Tech Labs') }}<br>
{{ config('app.name', 'ModernPOS') }}
@endcomponent
