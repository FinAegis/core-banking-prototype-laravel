<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>FinAegis CGO Investment Certificate</title>
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .certificate {
            width: 100%;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .certificate-content {
            background: white;
            width: 90%;
            max-width: 700px;
            padding: 60px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .logo {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 30px;
        }
        h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .subtitle {
            color: #666;
            font-size: 18px;
            margin-bottom: 40px;
        }
        .detail-row {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 20px;
            color: #333;
        }
        .tier-badge {
            display: inline-block;
            padding: 10px 30px;
            border-radius: 50px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 20px 0;
        }
        .tier-bronze { background: #cd7f32; color: white; }
        .tier-silver { background: #c0c0c0; color: #333; }
        .tier-gold { background: #ffd700; color: #333; }
        .signature-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        .signature {
            font-style: italic;
            color: #666;
            margin-top: 10px;
        }
        .certificate-number {
            position: absolute;
            bottom: 30px;
            right: 30px;
            font-size: 12px;
            color: #999;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(0,0,0,0.05);
            font-weight: bold;
            z-index: 0;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="watermark">FINAEGIS</div>
        <div class="certificate-content">
            <div class="logo">FinAegis</div>
            
            <h1>Certificate of Investment</h1>
            <p class="subtitle">Continuous Growth Offering (CGO)</p>
            
            <div class="detail-row">
                <span class="detail-label">This certifies that</span>
                <span class="detail-value">{{ $investment->user->name }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Has invested</span>
                <span class="detail-value">${{ number_format($investment->amount, 2) }} USD</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Acquiring</span>
                <span class="detail-value">{{ number_format($investment->shares_purchased, 4) }} shares</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Representing</span>
                <span class="detail-value">{{ number_format($investment->ownership_percentage, 6) }}% ownership</span>
            </div>
            
            <div class="tier-badge tier-{{ $investment->tier }}">
                {{ ucfirst($investment->tier) }} Tier Investor
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Investment Date</span>
                <span class="detail-value">{{ $investment->created_at->format('F d, Y') }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Round</span>
                <span class="detail-value">#{{ $investment->round->round_number }} at ${{ number_format($investment->share_price, 2) }}/share</span>
            </div>
            
            <div class="signature-section">
                <p><strong>FinAegis Ltd.</strong></p>
                <p class="signature">Digitally signed and verified</p>
                <p style="font-size: 12px; color: #999; margin-top: 20px;">
                    This certificate confirms your contribution to the development of the FinAegis platform.
                </p>
            </div>
        </div>
        
        <div class="certificate-number">
            Certificate #{{ $investment->certificate_number }}
        </div>
    </div>
</body>
</html>