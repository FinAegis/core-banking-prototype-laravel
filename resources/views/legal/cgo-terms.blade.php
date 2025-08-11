<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CGO Terms and Conditions - FinAegis</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <x-platform-banners />
    <x-main-navigation />

    <div class="py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-8 lg:p-12">
                    <h1 class="text-3xl font-bold text-gray-900 mb-8">
                        Continuous Growth Offering (CGO) Terms and Conditions
                    </h1>
                    
                    <div class="prose prose-lg max-w-none">
                        <p class="text-gray-600 mb-6">
                            <strong>Effective Date:</strong> July 21, 2025<br>
                            <strong>Last Updated:</strong> {{ now()->format('F j, Y') }}
                        </p>

                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-8">
                            <p class="text-sm font-semibold text-yellow-800 mb-2">IMPORTANT NOTICE</p>
                            <p class="text-sm text-yellow-700">
                                This is a contribution to support the development of the FinAegis platform and movement for democratic banking. 
                                This is NOT an investment in securities, and contributors should not expect financial returns. 
                                Please read these terms carefully before participating.
                            </p>
                        </div>

                        <h2 class="text-2xl font-bold mt-8 mb-4">1. Nature of Contribution</h2>
                        <p>By participating in the FinAegis Continuous Growth Offering (CGO), you acknowledge and agree that:</p>
                        <ul class="list-disc pl-6 mb-4">
                            <li>Your contribution is a <strong>donation to support development</strong> of the FinAegis platform and the democratic banking movement</li>
                            <li>This is <strong>NOT an investment</strong> in securities or any financial instrument</li>
                            <li>You are <strong>NOT purchasing equity</strong>, shares, or any ownership interest in FinAegis Ltd. or any affiliated entity</li>
                            <li>You should <strong>NOT expect financial returns</strong>, dividends, or profit from your contribution</li>
                            <li>The certificates and benefits provided are <strong>symbolic recognition</strong> of your support</li>
                        </ul>

                        <h2 class="text-2xl font-bold mt-8 mb-4">2. Contribution Tiers and Benefits</h2>
                        
                        <h3 class="text-xl font-semibold mt-6 mb-3">Bronze Tier ($100 - $999)</h3>
                        <ul class="list-disc pl-6 mb-4">
                            <li>Digital Certificate of Support acknowledging your contribution to the movement</li>
                            <li>Recognition on our supporters page (optional)</li>
                            <li>Early access to platform features and updates</li>
                        </ul>

                        <h3 class="text-xl font-semibold mt-6 mb-3">Silver Tier ($1,000 - $9,999)</h3>
                        <ul class="list-disc pl-6 mb-4">
                            <li>All Bronze tier benefits</li>
                            <li>Monthly community calls with the development team</li>
                            <li>Digital "Idea Shareholding" certificate (symbolic recognition, not actual shares)</li>
                            <li>Input on feature prioritization through community voting</li>
                            <li>Special recognition as a "Founding Supporter"</li>
                        </ul>

                        <h3 class="text-xl font-semibold mt-6 mb-3">Gold Tier ($10,000+)</h3>
                        <ul class="list-disc pl-6 mb-4">
                            <li>All Silver tier benefits</li>
                            <li>Direct discussion with founders about potential future opportunities</li>
                            <li>Advisory role in platform development (non-binding)</li>
                            <li>Special recognition as a "Visionary Supporter"</li>
                            <li>Potential consideration for future equity opportunities (subject to separate agreements and regulatory compliance)</li>
                        </ul>

                        <h2 class="text-2xl font-bold mt-8 mb-4">3. Use of Contributions</h2>
                        <p>Your contributions will be used exclusively for:</p>
                        <ul class="list-disc pl-6 mb-4">
                            <li>Platform development and technical infrastructure</li>
                            <li>Regulatory compliance and legal fees</li>
                            <li>Team salaries and operational expenses</li>
                            <li>Marketing and community building</li>
                            <li>Research and development of democratic banking solutions</li>
                        </ul>

                        <h2 class="text-2xl font-bold mt-8 mb-4">4. No Guarantee of Success</h2>
                        <p>You acknowledge that:</p>
                        <ul class="list-disc pl-6 mb-4">
                            <li>The FinAegis platform is in development and success is not guaranteed</li>
                            <li>Regulatory approval for banking operations is not assured</li>
                            <li>The platform may pivot, change direction, or cease operations</li>
                            <li>Benefits described may be modified or discontinued</li>
                        </ul>

                        <h2 class="text-2xl font-bold mt-8 mb-4">5. Refund Policy</h2>
                        <p>
                            All contributions are <strong>final and non-refundable</strong>. Once processed, contributions cannot be returned 
                            as they are immediately allocated to development expenses.
                        </p>

                        <h2 class="text-2xl font-bold mt-8 mb-4">6. Tax Considerations</h2>
                        <p>
                            Contributors are responsible for any tax obligations related to their contribution. 
                            Contributions may not be tax-deductible. Please consult with a tax professional regarding your specific situation.
                        </p>

                        <h2 class="text-2xl font-bold mt-8 mb-4">7. Eligibility</h2>
                        <p>To participate in the CGO, you must:</p>
                        <ul class="list-disc pl-6 mb-4">
                            <li>Be at least 18 years of age</li>
                            <li>Have the legal capacity to enter into this agreement</li>
                            <li>Not be a resident of a jurisdiction where such contributions are prohibited</li>
                            <li>Acknowledge that this is a donation, not an investment</li>
                        </ul>

                        <h2 class="text-2xl font-bold mt-8 mb-4">8. Intellectual Property</h2>
                        <p>
                            All intellectual property developed using CGO contributions remains the property of FinAegis Ltd. 
                            Contributors receive no ownership rights to any intellectual property.
                        </p>

                        <h2 class="text-2xl font-bold mt-8 mb-4">9. Privacy and Data Protection</h2>
                        <p>
                            Your personal information will be handled in accordance with our Privacy Policy. 
                            We will not sell or share your information with third parties without your consent.
                        </p>

                        <h2 class="text-2xl font-bold mt-8 mb-4">10. Limitation of Liability</h2>
                        <p>
                            TO THE MAXIMUM EXTENT PERMITTED BY LAW, FINAEGIS LTD. SHALL NOT BE LIABLE FOR ANY INDIRECT, 
                            INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES ARISING FROM YOUR CONTRIBUTION.
                        </p>

                        <h2 class="text-2xl font-bold mt-8 mb-4">11. Governing Law</h2>
                        <p>
                            These terms shall be governed by the laws of [Jurisdiction], without regard to conflict of law principles.
                        </p>

                        <h2 class="text-2xl font-bold mt-8 mb-4">12. Modifications</h2>
                        <p>
                            We reserve the right to modify these terms at any time. Changes will be posted on this page 
                            with an updated "Last Updated" date.
                        </p>

                        <h2 class="text-2xl font-bold mt-8 mb-4">13. Contact Information</h2>
                        <p>
                            For questions about these terms or the CGO program, please contact:<br>
                            Email: info@finaegis.org<br>
                            Address: [To be determined]
                        </p>

                        <div class="bg-gray-100 p-6 rounded-lg mt-8">
                            <h3 class="font-semibold mb-2">Acknowledgment</h3>
                            <p class="text-sm text-gray-700">
                                By participating in the CGO, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions. 
                                You confirm that you are contributing to support the development of democratic banking solutions and not making an investment 
                                for financial returns.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.footer')
</body>
</html>