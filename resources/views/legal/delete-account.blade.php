<x-guest-layout>
    @section('title', 'Delete Your Zelta Account')
    @section('description', 'This page explains how to delete your Zelta account. It also says what we delete and which records we may keep where applicable law requires it.')

    @php
        $isFinAegisSite = app()->environment('demo') || config('brand.show_promo_pages');
        // The developer name must match the developer name on the Google Play listing.
        $developer = $isFinAegisSite ? 'FinAegis' : config('brand.legal_entity');
    @endphp

    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gray-50 border-b">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <h1 class="text-4xl font-bold text-gray-900">Delete Your Account</h1>
                <p class="mt-4 text-lg text-gray-600">This page is about the <strong>Zelta</strong> app. Its developer is <strong>{{ $developer }}</strong>.</p>
                <p class="mt-2 text-sm text-gray-500">Last updated on {{ \Carbon\Carbon::parse('2026-11-24')->format('F j, Y') }}.</p>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="prose prose-lg max-w-none">

                <p>You can delete your Zelta account and its data at any time. There are two ways to do it.</p>

                <h2>Delete your account in the app</h2>
                <p>This is the fastest way.</p>
                <ol>
                    <li>Open the Zelta app and sign in.</li>
                    <li>Open the <strong>Profile</strong> tab.</li>
                    <li>Scroll to the bottom. Below <strong>Sign Out</strong>, tap <strong>Delete Account</strong>.</li>
                    <li>A window called <strong>Delete Account</strong> opens. Read it, then tap <strong>Delete My Account</strong>.</li>
                </ol>
                <p>The deletion cannot be undone. The app signs you out straight away. We also end your sign-in on your other devices.</p>
                <p>Before you delete your account, move all the money in your wallet to another wallet that you control. After the deletion, you cannot open this wallet in the app again.</p>

                <h2>Delete your account by email</h2>
                <p>If you cannot use the app, write to <a href="mailto:support@zelta.app?subject=Delete%20my%20account">support@zelta.app</a> from the email address of your Zelta account. Use the subject line <strong>"Delete my account"</strong>. We check that the request comes from you and process it within 30 days.</p>
                <p>If you can no longer use that email address, write to us from any address. Include enough details for us to confirm who you are. Give your full legal name, your date of birth and roughly when you last used the app. We may ask for more proof before we go ahead.</p>

                <h2>What we delete</h2>
                <ul>
                    <li>We replace your name and email address with anonymous values.</li>
                    <li>We remove the link to your sign-in account. We also ask our sign-in provider to delete that account.</li>
                    <li>We delete the identity documents you uploaded.</li>
                    <li>We delete the devices you registered. This includes their notification tokens, sessions, notification history and notification settings.</li>
                    <li>We delete the activity list that the app shows you.</li>
                    <li>We delete your bank transfer profile. We also ask our bank transfer partner to delete your customer record.</li>
                    @unless($isFinAegisSite)
                        <li>If you applied for a card, we erase the details you gave for it. These are your name, contact details, address, date of birth, nationality and your answers to the card questions.</li>
                    @endunless
                    <li>If you paid for a subscription by card, we ask our payment processor to delete your customer record.</li>
                    <li>If you subscribed in the App Store or on Google Play, we ask the store to delete your subscriber data.</li>
                </ul>

                <h2>What we keep</h2>
                <p>Your account does not disappear completely. It stays as an anonymous entry, and the records below stay linked to it.</p>
                <ul>
                    <li>We keep the records of money you moved with Zelta. These are wallet transfers, bank transfers, payments, subscriptions and purchases in the app{{ $isFinAegisSite ? '' : ', and card top-ups' }}. The records keep the amounts, dates, wallet addresses and transaction references. We erase your bank details, free-text notes and technical details from them.</li>
                    <li>Your wallet addresses stay on record, but we stop watching them for new transactions.</li>
                    <li>We keep a coded form of your sign-in identifier and the time of the deletion. It stops an old session on a phone from opening a new account by mistake. The code cannot be turned back into the identifier.</li>
                    <li>We keep the proof that you agreed to our terms and to your subscription. We need it in case of a legal claim.</li>
                    <li>We keep security records, such as changes of the device linked to your account. We remove your network address and device details from them.</li>
                    <li>We keep identity verification and anti-money-laundering records where the law requires it.</li>
                    @unless($isFinAegisSite)
                        <li>If you have a card, the card and the money on it stay with the card issuer. The card issuer&rsquo;s terms apply to them.</li>
                    @endunless
                </ul>

                <h2>How long we keep records</h2>
                <p>Some records may be kept after the account is closed where applicable law requires it. Examples are the rules on anti-money-laundering, sanctions, tax and consumer protection. The exact periods depend on the country. The periods below are the usual range.</p>
                <ul>
                    <li>Identity verification records are kept for 5 to 10 years after the account is closed, where applicable law requires it.</li>
                    <li>Transaction history and blockchain transaction references are kept for 5 to 10 years.</li>
                    <li>Compliance, sanctions screening and fraud investigation records are kept for up to 10 years, where applicable law requires it.</li>
                    <li>Records under an active legal hold or a request from law enforcement are kept for as long as the hold lasts.</li>
                </ul>
                <p>When the retention period ends, we delete the remaining records or make them fully anonymous.</p>

                <h2>Blockchain transactions</h2>
                <p>We cannot delete transactions recorded on public blockchains. This covers Solana, Polygon, Base, Arbitrum, Ethereum and the other networks we support. These blockchains are public records that cannot be changed, so the transactions stay visible after you delete your account. We can only remove the link between you and those addresses in our own systems.</p>

                <h2>Questions</h2>
                <p>Email <a href="mailto:support@zelta.app">support@zelta.app</a> with any question. Our <a href="{{ route('legal.privacy') }}">Privacy Policy</a> explains in more detail how we handle your data.</p>

            </div>
        </div>
    </div>
</x-guest-layout>
