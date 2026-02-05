@component('mail::message')
# Subscription Upgraded! ⬆️

Hello {{ $subscription->business->owner->name }},

Great news! Your subscription has been upgraded.

## Upgrade Details:

- **Previous Plan:** {{ $oldPlanName }}
- **New Plan:** {{ $subscription->plan->name }}
- **Billing Cycle:** {{ $billingLabel }}
- **Amount:** {{ number_format($subscription->amount, 2) }} {{ $subscription->currency }}
- **Valid Until:** {{ $subscription->ends_at ? $subscription->ends_at->format('F j, Y') : 'N/A' }}

Enjoy your enhanced features!

@component('mail::button', ['url' => url('/dashboard')])
View Dashboard
@endcomponent

Thank you for growing with us!

A payment receipt has been attached for your records.

Thanks,<br>
{{ config('app.company_name', 'Doitix Tech Labs') }}<br>
{{ config('app.name', 'ModernPOS') }}
@endcomponent
