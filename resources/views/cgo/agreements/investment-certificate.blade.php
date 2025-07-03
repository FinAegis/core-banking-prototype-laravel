<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Investment Certificate - {{ $certificate_number }}</title>
    <style>
        @page {
            margin: 1cm;
            size: landscape;
        }
        body {
            font-family: 'Georgia', serif;
            margin: 0;
            padding: 20px;
            background-color: #fff;
            position: relative;
        }
        .certificate-border {
            border: 15px solid #2c3e50;
            border-image: repeating-linear-gradient(45deg, #2c3e50, #34495e 10px, #2c3e50 10px, #34495e 20px) 15;
            padding: 40px;
            height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120pt;
            color: rgba(44, 62, 80, 0.05);
            font-weight: bold;
            z-index: 1;
        }
        .content {
            position: relative;
            z-index: 2;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .company-name {
            font-size: 36pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .certificate-title {
            font-size: 28pt;
            color: #34495e;
            margin-bottom: 20px;
            font-style: italic;
        }
        .certificate-number {
            font-size: 14pt;
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .body-text {
            font-size: 16pt;
            line-height: 1.8;
            text-align: center;
            margin: 30px 0;
            color: #2c3e50;
        }
        .investor-name {
            font-size: 24pt;
            font-weight: bold;
            color: #c0392b;
            display: inline-block;
            border-bottom: 2px solid #c0392b;
            padding: 0 10px;
            margin: 0 10px;
        }
        .details-section {
            margin: 40px auto;
            max-width: 600px;
            border: 2px solid #34495e;
            padding: 20px;
            background-color: #ecf0f1;
            border-radius: 10px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            font-size: 14pt;
        }
        .detail-label {
            font-weight: bold;
            color: #34495e;
        }
        .detail-value {
            color: #2c3e50;
        }
        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 60px;
        }
        .signature-block {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-bottom: 2px solid #2c3e50;
            margin-bottom: 5px;
            height: 50px;
        }
        .signature-label {
            font-size: 12pt;
            color: #7f8c8d;
        }
        .seal-section {
            position: absolute;
            bottom: 40px;
            right: 60px;
            width: 120px;
            height: 120px;
            border: 3px solid #c0392b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #c0392b;
            font-size: 14pt;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.9);
        }
        .issue-date {
            position: absolute;
            bottom: 20px;
            left: 60px;
            font-size: 10pt;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="certificate-border">
        <div class="watermark">{{ $company_name }}</div>
        
        <div class="content">
            <div class="header">
                <div class="company-name">{{ $company_name }}</div>
                <div class="certificate-title">Certificate of Investment</div>
                <div class="certificate-number">Certificate No: {{ $certificate_number }}</div>
            </div>

            <div class="body-text">
                This certifies that<br>
                <span class="investor-name">{{ $investor_name }}</span><br>
                is the registered holder of
            </div>

            <div class="details-section">
                <div class="detail-row">
                    <span class="detail-label">Number of Shares:</span>
                    <span class="detail-value">{{ number_format($shares_purchased, 4) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Share Price:</span>
                    <span class="detail-value">{{ $currency }} {{ number_format($share_price, 4) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Investment:</span>
                    <span class="detail-value">{{ $currency }} {{ number_format($investment_amount, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ownership Percentage:</span>
                    <span class="detail-value">{{ number_format($ownership_percentage, 6) }}%</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Investment Tier:</span>
                    <span class="detail-value">{{ $tier }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Investment Date:</span>
                    <span class="detail-value">{{ $investment_date }}</span>
                </div>
            </div>

            <div class="body-text" style="font-size: 12pt; margin-top: 20px;">
                in {{ $company_name }}, subject to the terms and conditions of the Investment Agreement<br>
                and the Articles of Association of the Company.
            </div>

            <div class="signatures">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ $signatures['ceo'] }}<br>Chief Executive Officer</div>
                </div>
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ $signatures['cfo'] }}<br>Chief Financial Officer</div>
                </div>
            </div>
        </div>

        <div class="seal-section">
            OFFICIAL<br>SEAL
        </div>

        <div class="issue-date">
            Issued on: {{ $issue_date }}
        </div>
    </div>
</body>
</html>