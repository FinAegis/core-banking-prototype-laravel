<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Investment Agreement - {{ $agreement_number }}</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        h1 {
            font-size: 24pt;
            margin-bottom: 10px;
        }
        h2 {
            font-size: 18pt;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        h3 {
            font-size: 14pt;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .party-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .investment-details {
            margin: 20px 0;
            border: 1px solid #ddd;
            padding: 15px;
        }
        .terms-list {
            margin: 10px 0;
        }
        .terms-list li {
            margin-bottom: 10px;
        }
        .signature-section {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-box {
            display: inline-block;
            width: 45%;
            margin-top: 50px;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            height: 40px;
        }
        .page-break {
            page-break-before: always;
        }
        .disclaimer {
            font-size: 10pt;
            color: #666;
            margin-top: 30px;
            padding: 10px;
            border: 1px solid #ddd;
            background-color: #fafafa;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVESTMENT AGREEMENT</h1>
        <p>Agreement Number: {{ $agreement_number }}</p>
        <p>Date: {{ $agreement_date }}</p>
    </div>

    <h2>1. PARTIES</h2>
    
    <div class="party-section">
        <h3>Company:</h3>
        <p><strong>{{ $company['name'] }}</strong><br>
        Registration Number: {{ $company['registration'] }}<br>
        Address: {{ $company['address'] }}<br>
        Email: {{ $company['email'] }}</p>
    </div>

    <div class="party-section">
        <h3>Investor:</h3>
        <p><strong>{{ $investor['name'] }}</strong><br>
        Email: {{ $investor['email'] }}<br>
        Address: {{ $investor['address'] }}<br>
        Country: {{ $investor['country'] }}</p>
    </div>

    <h2>2. INVESTMENT DETAILS</h2>
    
    <div class="investment-details">
        <table style="width: 100%;">
            <tr>
                <td><strong>Investment Amount:</strong></td>
                <td>{{ $investment_details['currency'] }} {{ number_format($investment_details['amount'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Number of Shares:</strong></td>
                <td>{{ number_format($investment_details['shares'], 4) }}</td>
            </tr>
            <tr>
                <td><strong>Price per Share:</strong></td>
                <td>{{ $investment_details['currency'] }} {{ number_format($investment_details['share_price'], 4) }}</td>
            </tr>
            <tr>
                <td><strong>Ownership Percentage:</strong></td>
                <td>{{ number_format($investment_details['ownership_percentage'], 6) }}%</td>
            </tr>
            <tr>
                <td><strong>Investment Tier:</strong></td>
                <td>{{ $investment_details['tier'] }}</td>
            </tr>
            <tr>
                <td><strong>Funding Round:</strong></td>
                <td>{{ $investment_details['round_name'] }}</td>
            </tr>
            <tr>
                <td><strong>Pre-Money Valuation:</strong></td>
                <td>{{ $investment_details['currency'] }} {{ number_format($investment_details['valuation'], 0) }}</td>
            </tr>
        </table>
    </div>

    <h2>3. INVESTMENT TERMS</h2>
    
    <ul class="terms-list">
        <li><strong>Lock-in Period:</strong> {{ $terms['lock_in_period'] }}</li>
        <li><strong>Dividend Rights:</strong> {{ $terms['dividend_rights'] }}</li>
        <li><strong>Voting Rights:</strong> {{ $terms['voting_rights'] }}</li>
        <li><strong>Transfer Restrictions:</strong> {{ $terms['transfer_restrictions'] }}</li>
        <li><strong>Dilution Protection:</strong> {{ $terms['dilution_protection'] }}</li>
        <li><strong>Information Rights:</strong> {{ $terms['information_rights'] }}</li>
        @if(isset($terms['board_observer']))
        <li><strong>Board Observer Rights:</strong> {{ $terms['board_observer'] }}</li>
        @endif
    </ul>

    <div class="page-break"></div>

    <h2>4. REPRESENTATIONS AND WARRANTIES</h2>
    
    <h3>4.1 Investor Representations</h3>
    <p>The Investor represents and warrants that:</p>
    <ul>
        <li>They have full legal capacity to enter into this Agreement</li>
        <li>They have conducted their own due diligence and investment analysis</li>
        <li>They understand the risks associated with this investment</li>
        <li>The funds used for this investment are from legitimate sources</li>
        <li>They are investing for their own account and not as an agent</li>
    </ul>

    <h3>4.2 Company Representations</h3>
    <p>The Company represents and warrants that:</p>
    <ul>
        <li>It is duly incorporated and validly existing</li>
        <li>It has full corporate power to enter into this Agreement</li>
        <li>The shares will be validly issued and fully paid</li>
        <li>All material information has been disclosed to the Investor</li>
    </ul>

    <h2>5. INVESTMENT RISKS</h2>
    
    <div class="disclaimer">
        <p><strong>WARNING:</strong> This investment carries significant risks. The Investor acknowledges and accepts the following risks:</p>
        <ul>
            @foreach($risks as $risk)
            <li>{{ $risk }}</li>
            @endforeach
        </ul>
    </div>

    <h2>6. CONFIDENTIALITY</h2>
    
    <p>Both parties agree to maintain the confidentiality of all non-public information disclosed in connection with this investment and not to disclose such information to third parties without prior written consent, except as required by law.</p>

    <h2>7. GOVERNING LAW</h2>
    
    <p>This Agreement shall be governed by and construed in accordance with the laws of [Jurisdiction], without regard to its conflict of law provisions.</p>

    <h2>8. ENTIRE AGREEMENT</h2>
    
    <p>This Agreement constitutes the entire agreement between the parties with respect to the subject matter hereof and supersedes all prior negotiations, representations, or agreements, whether written or oral.</p>

    <div class="signature-section">
        <h2>9. SIGNATURES</h2>
        
        <div class="signature-box">
            <p><strong>INVESTOR</strong></p>
            <div class="signature-line"></div>
            <p>Name: {{ $investor['name'] }}<br>
            Date: _______________________</p>
        </div>

        <div class="signature-box" style="float: right;">
            <p><strong>COMPANY</strong></p>
            <div class="signature-line"></div>
            <p>Name: _______________________<br>
            Title: _______________________<br>
            Date: _______________________</p>
        </div>
    </div>
</body>
</html>