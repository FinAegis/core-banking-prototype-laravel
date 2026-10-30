<x-guest-layout>
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gray-50 border-b">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <h1 class="text-4xl font-bold text-gray-900">Terms of Service</h1>
                <p class="mt-4 text-lg text-gray-600">Last updated: December 23, 2025</p>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="prose prose-lg max-w-none">
                @php $isFinAegisSite = app()->environment('demo') || config('brand.show_promo_pages'); @endphp
                {{-- TODO(regulatory): full legal review of these Terms by counsel pending — see docs/REGULATORY-CLAIMS.md --}}

                <h2 id="regulatory-status">Regulatory status</h2>
                @if($isFinAegisSite)
                <p>FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.</p>
                <p>FinAegis is a software project and does not hold any banking, e-money, payment-institution, crypto-asset service provider or token-issuer licence or authorisation.</p>
                @else
                <p>Zelta is non-custodial wallet software. Zelta and its operator are not a bank, electronic money institution, payment institution or crypto-asset service provider, and do not hold, exchange or transmit users&rsquo; funds. Any card or off-ramp services will be provided by licensed third parties under their own terms.</p>
                @endif

                <h2>1. Acceptance of Terms</h2>
                @if($isFinAegisSite)
                <p>By accessing and using the {{ config('brand.name') }} platform ("Service"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>
                @else
                <p>By accessing and using Zelta ("Service"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>
                @endif

                <h2>2. Description of Service</h2>
                @if($isFinAegisSite)
                <p>{{ config('brand.name') }} is open-source software. This website provides information about the software and a demonstration environment that uses test data.</p>
                @else
                <p>Zelta is non-custodial wallet software that lets you hold, send and receive digital assets using wallets you control.</p>
                @endif

                <h2>3. User Accounts</h2>
                <h3>3.1 Account Registration</h3>
                <p>To use our Service, you must register for an account and provide accurate, complete, and current information. You are responsible for safeguarding your account credentials and for all activities that occur under your account.</p>

                <h3>3.2 Eligibility</h3>
                <p>You must be at least 18 years old and legally capable of entering into binding contracts to use our Service. Our Service is not available in all jurisdictions.</p>

                <h3>3.3 Identity Verification</h3>
                <p>Third-party providers (for example, for card or fiat-conversion services, where available) may require you to verify your identity under their own terms.</p>
                @unless($isFinAegisSite)
                <p>Zelta Trust levels and on-chain attestations are in-app features. They are not a regulated identity-verification or KYC service, not an eIDAS electronic identification or trust service, and are not intended to satisfy any third party's legal KYC or AML obligations.</p>
                @endunless

                <h2>4. Funds, Fees and Limits</h2>
                <h3>4.1 Your Funds</h3>
                @if($isFinAegisSite)
                <p>See <a href="#regulatory-status">Regulatory status</a> above.</p>
                @else
                <p>Converting stablecoins to fiat, where available, will be provided by licensed third parties under their own terms.</p>
                @endif

                <h3>4.2 Transaction Fees</h3>
                <p>Applicable fees are shown in the app before you confirm a transaction. Fees may be updated from time to time, and you will be notified of any fee changes in advance.</p>

                <h3>4.3 Transaction Limits</h3>
                <p>We may impose daily, monthly, or other periodic transaction limits for security purposes.</p>

                <h2>5. Prohibited Activities</h2>
                <p>You agree not to use our Service for:</p>
                <ul>
                    <li>Any illegal activities or violation of applicable laws</li>
                    <li>Money laundering, terrorist financing, or other financial crimes</li>
                    <li>Fraud, impersonation, or unauthorized access</li>
                    <li>Market manipulation or insider trading</li>
                    <li>Circumventing our security measures or access controls</li>
                </ul>

                <h2>6. Risk Disclosure</h2>
                <h3>6.1 General Risks</h3>
                <p>Using digital assets involves inherent risks. While we take measures to secure the software and your data, you acknowledge that:</p>
                <ul>
                    <li>Currency values may fluctuate and cause losses</li>
                    <li>Technical issues may temporarily affect service availability</li>
                    <li>Regulatory changes may impact service features</li>
                    <li>Third-party providers may experience operational issues</li>
                </ul>

                <h3>6.2 Digital Asset Risks</h3>
                <p>Digital assets, including stablecoins, are subject to additional risks including extreme price volatility, regulatory uncertainty, and technological risks.</p>

                <h2>7. Data Protection and Privacy</h2>
                <p>We are committed to protecting your personal data in accordance with applicable privacy laws. Please review our Privacy Policy for detailed information about how we collect, use, and protect your data.</p>

                <h2>8. Intellectual Property</h2>
                <p>The Service and its original content, features, and functionality are and will remain the exclusive property of {{ config('brand.legal_entity') }} and its licensors. The Service is protected by copyright, trademark, and other laws.</p>

                <h2>9. Limitation of Liability</h2>
                <p>To the maximum extent permitted by law, {{ config('brand.legal_entity') }} shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses.</p>

                <h2>10. Indemnification</h2>
                <p>You agree to defend, indemnify, and hold harmless {{ config('brand.legal_entity') }} and its affiliates from and against any claims, liabilities, damages, judgments, awards, losses, costs, expenses, or fees arising out of or relating to your violation of these Terms or your use of the Service.</p>

                <h2>11. Termination</h2>
                <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.</p>

                <h2>12. Governing Law</h2>
                <p>These Terms shall be interpreted and governed by the laws of the jurisdiction in which {{ config('brand.legal_entity') }} is incorporated. Any legal action or proceeding arising under these Terms will be brought exclusively in the courts of that jurisdiction.</p>

                <h2>13. Changes to Terms</h2>
                <p>We reserve the right to modify or replace these Terms at any time. If a revision is material, we will try to provide at least 30 days notice prior to any new terms taking effect.</p>

                <h2>14. Dispute Resolution</h2>
                <h3>14.1 Binding Arbitration</h3>
                <p>Any dispute, claim, or controversy arising out of or relating to these Terms shall be settled by binding arbitration in accordance with the applicable arbitration rules of the jurisdiction in which {{ config('brand.legal_entity') }} is incorporated.</p>

                <h3>14.2 Class Action Waiver</h3>
                <p>You agree that any dispute resolution proceedings will be conducted only on an individual basis and not in a class, consolidated, or representative action.</p>

                <h2>15. Severability</h2>
                <p>If any provision of these Terms is held to be invalid or unenforceable by a court, the remaining provisions of these Terms will remain in effect.</p>

                <h2>16. Contact Information</h2>
                <p>If you have any questions about these Terms, please contact us at:</p>
                {{-- TODO(regulatory): operator legal entity details pending — see docs/REGULATORY-CLAIMS.md --}}
                <div class="bg-gray-50 p-6 rounded-lg mt-6">
                    <p><strong>{{ config('brand.legal_entity') }} Legal Department</strong><br>
                    Email: {{ config('brand.legal_email') }}<br>
                    Address: {{ config('brand.legal_jurisdiction') }}</p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-8">
                    <p class="text-sm text-blue-800">
                        <strong>Important:</strong> These Terms of Service constitute a legally binding agreement. Please read them carefully and contact us if you have any questions before using our Service.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>