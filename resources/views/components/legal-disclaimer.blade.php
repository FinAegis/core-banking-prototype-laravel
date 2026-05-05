{{-- Legal Disclaimer Component (Rizon-style) --}}
{{-- Usage: <x-legal-disclaimer /> or <x-legal-disclaimer :compact="true" /> --}}

@props(['compact' => false])

@if($compact)
    <p class="text-xs text-slate-400 leading-relaxed">
        {{ config('brand.name', 'Zelta') }} is a technology platform providing a user interface for services offered by independent third-party providers. {{ config('brand.name', 'Zelta') }} does not offer, hold, or transmit funds or provide financial, custodial, or regulated services. All wallet functionality is non-custodial &mdash; private keys remain under exclusive user control. Financial services are provided by third-party licensed providers. All investments carry risks, including total loss. The user is responsible for safeguarding their passkeys and any device-bound credentials used to authorize transactions.
    </p>
@else
    <div class="space-y-3 text-sm text-slate-500 leading-relaxed">
        <p>
            <strong>{{ config('brand.name', 'Zelta') }}</strong> is a technology platform that provides a user interface enabling access to services offered by independent third-party providers. {{ config('brand.name', 'Zelta') }} does not offer, hold, or transmit funds, crypto-assets, or provide any financial, custodial, or regulated services.
        </p>
        <p>
            All wallet functionality is powered by non-custodial wallet infrastructure. Wallets are created and controlled solely by users and are not accessible by {{ config('brand.name', 'Zelta') }}. All private keys remain under the exclusive control of the user.
        </p>
        <p>
            Any financial or payment-related services, including the processing of digital asset transfers, are provided solely by third-party licensed financial service providers operating under their own regulatory authorizations.
        </p>
        <p>
            The user is responsible for safeguarding their passkeys and any device-bound credentials used to authorize transactions. If passkey enrollments are lost across all of the user&rsquo;s devices, account access may not be recoverable.
        </p>
        <p>
            All forms of investments carry risks, including the risk of losing all of the invested amount. Such activities may not be suitable for everyone.
        </p>
    </div>
@endif
