{{-- Legal Disclaimer Component (Rizon-style) --}}
{{-- Usage: <x-legal-disclaimer /> or <x-legal-disclaimer :compact="true" /> --}}
{{-- Brand-aware regulatory status (canonical wording: docs/REGULATORY-CLAIMS.md copy kit). --}}
{{-- finaegis.org (demo / promo pages) renders F1 (+ F5 in the full variant); zelta.app renders Z1. --}}

@props(['compact' => false])

@php
    $isFinAegisSite = app()->environment('demo') || config('brand.show_promo_pages');
@endphp

@if($compact)
    <p class="text-xs text-slate-400 leading-relaxed">
        @if($isFinAegisSite)
            FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
        @else
            Zelta is non-custodial wallet software. Zelta and its operator are not a bank, electronic money institution, payment institution or crypto-asset service provider, and do not hold, exchange or transmit users&rsquo; funds. Any card or off-ramp services will be provided by licensed third parties under their own terms. The user is responsible for safeguarding their passkeys and any device-bound credentials used to authorize transactions.
        @endif
    </p>
@else
    <div class="space-y-3 text-sm text-slate-500 leading-relaxed">
        @if($isFinAegisSite)
            <p>
                <strong>FinAegis</strong> is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
            </p>
            <p>
                FinAegis is a software project and does not hold any banking, e-money, payment-institution, crypto-asset service provider or token-issuer licence or authorisation.
            </p>
        @else
            <p>
                <strong>Zelta</strong> is non-custodial wallet software. Zelta and its operator are not a bank, electronic money institution, payment institution or crypto-asset service provider, and do not hold, exchange or transmit users&rsquo; funds. Any card or off-ramp services will be provided by licensed third parties under their own terms.
            </p>
            <p>
                All wallet functionality is powered by non-custodial wallet infrastructure. Wallets are created and controlled solely by users and are not accessible by Zelta. All private keys remain under the exclusive control of the user.
            </p>
            <p>
                The user is responsible for safeguarding their passkeys and any device-bound credentials used to authorize transactions. If passkey enrollments are lost across all of the user&rsquo;s devices, account access may not be recoverable.
            </p>
        @endif
    </div>
@endif
