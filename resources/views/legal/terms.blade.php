<x-guest-layout>
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gray-50 border-b">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <h1 class="text-4xl font-bold text-gray-900">Terms of Service</h1>
                <p class="mt-4 text-lg text-gray-600">Last updated: November 23, 2026</p>
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
                <p>Virtual cards are issued and run by a licensed card-issuing partner under its own licence and terms. When you add money to a card, you send USDC from your own wallet to your own account with that partner. On your instruction, which the app passes to the partner, the partner converts it to US dollars and loads your card. Zelta does not take possession of the money at any point and does not carry out the conversion. See <a href="#virtual-cards">4.4 Virtual Cards</a>.</p>
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

                @unless($isFinAegisSite)
                <h3 id="virtual-cards">4.4 Virtual Cards</h3>
                <p>Where available, you can apply for a virtual card in the app. The card is issued and run by a licensed card-issuing partner under its own terms and fee schedule. Zelta is not the card issuer.</p>
                <p>The card is offered only to people who pass the partner&rsquo;s identity check. It is not available to citizens or residents of the United States.</p>
                <p>When you apply, we send your details to the partner so that it can open your card and check your identity. Our <a href="{{ route('legal.privacy') }}">Privacy Policy</a> explains which details we send.</p>
                <p>You add money by sending USDC from your own Zelta wallet to your card account at the partner. You confirm every send yourself. The partner changes the USDC to US dollars and adds it to your card.</p>
                <p>Before you confirm, the app shows the partner&rsquo;s fees and about how much your card will receive. The final amount can differ a little because of the exchange rate. Your first top-up also pays the card opening fee shown in the app.</p>
                <p>Send only USDC on a network shown in the app. Other tokens, or tokens sent on another network, may not be credited and may be lost.</p>
                <p>Send at least the minimum amount shown in the app. A smaller amount is not loaded to your card automatically.</p>
                <p>A card that still holds money cannot be cancelled in the app. Moving money from the card back to your wallet is not available at this time.</p>
                <p>Your card number, expiry date and security code are shown only by the partner&rsquo;s secure page inside the app. Zelta does not store them.</p>
                <p>Online shops may ask for a one-time verification code. The app shows it after you confirm it is you. Never share this code with anyone.</p>
                <p>Card payments, refunds and disputes are handled by the partner under its terms. Contact our support and we will help you reach the partner.</p>
                <p>The partner or our support team may freeze or close a card, for example for security or legal reasons.</p>
                @endunless

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
                <p>Nothing in these Terms limits or excludes liability for death or personal injury caused by negligence, for fraud, for intentional misconduct or gross negligence, or any other liability that cannot be limited or excluded under applicable law, including your mandatory rights as a consumer.</p>

                <h2>10. Indemnification</h2>
                <p>You agree to defend, indemnify, and hold harmless {{ config('brand.legal_entity') }} and its affiliates from and against any claims, liabilities, damages, judgments, awards, losses, costs, expenses, or fees arising out of or relating to your violation of these Terms or your use of the Service.</p>

                <h2>11. Termination</h2>
                <p>You can stop using the Service and delete your account at any time.</p>
                <p>We may suspend or close your account at once if you seriously breach these Terms, if the law requires it, or to protect you, other users or the Service. In other cases we give you at least 30 days&rsquo; notice.</p>
                @unless($isFinAegisSite)
                <p>Closing your Zelta account does not affect the funds in wallets you control. A virtual card is closed under the card partner&rsquo;s terms.</p>
                @endunless

                <h2>12. Governing Law</h2>
                @if($isFinAegisSite)
                <p>These Terms shall be interpreted and governed by the laws of the jurisdiction in which {{ config('brand.legal_entity') }} is incorporated. Any legal action or proceeding arising under these Terms will be brought exclusively in the courts of that jurisdiction.</p>
                @else
                <p>These Terms are governed by the laws of the Republic of Lithuania. If you are a consumer, you also keep the protection of the mandatory laws of the country where you live.</p>
                @endif

                <h2>13. Changes to Terms</h2>
                <p>We may change these Terms. We give at least 30 days&rsquo; notice of a material change. If you do not agree with it, you can stop using the Service and delete your account before the change takes effect.</p>

                <h2>14. Dispute Resolution</h2>
                @if($isFinAegisSite)
                <h3>14.1 Binding Arbitration</h3>
                <p>Any dispute, claim, or controversy arising out of or relating to these Terms shall be settled by binding arbitration in accordance with the applicable arbitration rules of the jurisdiction in which {{ config('brand.legal_entity') }} is incorporated.</p>

                <h3>14.2 Class Action Waiver</h3>
                <p>You agree that any dispute resolution proceedings will be conducted only on an individual basis and not in a class, consolidated, or representative action.</p>
                @else
                <h3>14.1 Complaints</h3>
                <p>If something goes wrong, contact our support first at {{ config('brand.support_email') }}. We reply within 14 days.</p>

                <h3>14.2 Courts and out-of-court resolution</h3>
                <p>Disputes are decided by the courts of the Republic of Lithuania. If you are a consumer, you may also bring a claim in the courts of the country where you live.</p>
                <p>If you are a consumer and we cannot solve a complaint together, you may ask the State Consumer Rights Protection Authority of Lithuania (<a href="https://www.vvtat.lt" rel="noopener">www.vvtat.lt</a>) to resolve the dispute out of court.</p>
                <p>Disputes about card payments are handled by the card partner under its terms. We will help you reach it.</p>
                @endif

                <h2>15. Severability</h2>
                <p>If any provision of these Terms is held to be invalid or unenforceable by a court, the remaining provisions of these Terms will remain in effect.</p>

                <h2>16. Contact Information</h2>
                <p>If you have any questions about these Terms, please contact us at:</p>
                <div class="bg-gray-50 p-6 rounded-lg mt-6">
                    <p><strong>{{ config('brand.legal_entity') }} Legal Department</strong><br>
                    Email: {{ config('brand.legal_email') }}<br>
                    @include('legal.partials.operator')</p>
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