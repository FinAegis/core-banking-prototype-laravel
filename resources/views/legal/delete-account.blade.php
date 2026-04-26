<x-guest-layout>
    @section('title', 'Delete Your Account — Zelta')
    @section('description', 'How to delete your Zelta account, what data is deleted, and what records FinAegis retains under financial-services law.')

    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gray-50 border-b">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <h1 class="text-4xl font-bold text-gray-900">Delete Your Account</h1>
                <p class="mt-4 text-lg text-gray-600">App: <strong>Zelta</strong> &middot; Developer: <strong>FinAegis</strong></p>
                <p class="mt-2 text-sm text-gray-500">Last updated: {{ \Carbon\Carbon::parse('2026-04-26')->format('F j, Y') }}</p>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="prose prose-lg max-w-none">

                <p>You can request deletion of your Zelta account and associated data at any time. There are two ways to do it.</p>

                <h2>Option 1 &mdash; Delete in the app (fastest)</h2>
                <ol>
                    <li>Open the Zelta app and sign in.</li>
                    <li>Go to <strong>Profile &rarr; Settings &rarr; Security &amp; Privacy</strong>.</li>
                    <li>Tap <strong>Delete Account</strong>.</li>
                    <li>Confirm with your password.</li>
                </ol>
                <p>Your account is queued for deletion immediately and your sessions are terminated.</p>

                <h2>Option 2 &mdash; Delete by email</h2>
                <p>If you cannot access the app, email <a href="mailto:support@zelta.app?subject=Delete%20my%20account">support@zelta.app</a> from the email address on your Zelta account, with the subject line <strong>"Delete my account"</strong>. We will verify your identity and process the request within 30 days.</p>
                <p>If that email address is no longer accessible, contact us from any address and include enough information for us to verify your identity (full legal name, date of birth, last four digits of any linked card, approximate date of last activity). We may request additional verification before proceeding.</p>

                <h2>What gets deleted</h2>
                <ul>
                    <li>Profile information (name, email, phone, avatar)</li>
                    <li>Wallet preferences and saved recipients</li>
                    <li>Device shards and passkey credentials</li>
                    <li>In-app activity (notifications, rewards XP, dismissed banners)</li>
                    <li>Push notification tokens</li>
                    <li>Linked virtual cards (deactivated immediately)</li>
                </ul>

                <h2>What is retained, and for how long</h2>
                <p>FinAegis operates as a regulated financial-services provider. Some records must be retained after account closure to comply with anti-money-laundering, sanctions, tax, and consumer-protection law. Specific retention periods depend on the jurisdiction in which your account was held; the figures below reflect the typical range.</p>

                <ul>
                    <li><strong>KYC and identity-verification records</strong> &mdash; 5&ndash;10 years after account closure (EU AMLD; UK MLR 2017; equivalent regimes elsewhere).</li>
                    <li><strong>Transaction history and on-chain references</strong> &mdash; 5&ndash;10 years.</li>
                    <li><strong>Compliance, sanctions-screening, and fraud-investigation records</strong> &mdash; up to 10 years where required by financial regulators.</li>
                    <li><strong>Records subject to an active legal hold or law-enforcement request</strong> &mdash; for the duration of the hold.</li>
                </ul>

                <p>After the applicable retention period expires, the residual records are deleted or fully anonymized.</p>

                <h2>On-chain transactions</h2>
                <p>Transactions recorded on public blockchains (Ethereum, Polygon, Base, Arbitrum, BSC, Solana, and other supported networks) cannot be deleted by us. They are part of immutable public ledgers and remain visible after account deletion. We can only remove the link between your identity and those addresses from our own systems.</p>

                <h2>Questions?</h2>
                <p>Email <a href="mailto:support@zelta.app">support@zelta.app</a> or visit our <a href="{{ route('legal.privacy') }}">Privacy Policy</a> for more detail on how we handle your data.</p>

            </div>
        </div>
    </div>
</x-guest-layout>
