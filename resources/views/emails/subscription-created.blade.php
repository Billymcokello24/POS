@component('mail::message')
# New Subscription Created ✅

Hello {{ $recipient->name }},

Great news! A new subscription has been created for **{{ $subscription->business->name }}**.

## Subscription Details:

- **Plan:** {{ $subscription->plan_name }}
- **Billing Cycle:** {{ $billingLabel }}
- **Amount:** {{ number_format($subscription->amount, 2) }} {{ $subscription->currency }}
- **Valid From:** {{ $subscription->starts_at ? $subscription->starts_at->format('F j, Y') : 'N/A' }}
- **Valid Until:** {{ $subscription->ends_at ? $subscription->ends_at->format('F j, Y') : 'N/A' }}

@component('mail::button', ['url' => url('/admin/subscriptions')])
View Subscription
@endcomponent

A payment receipt has been attached to this email for your records.

Thanks,<br>
{{ config('app.company_name', 'Doitix Tech Labs') }}<br>
{{ config('app.name', 'ModernPOS') }}
@endcomponent
