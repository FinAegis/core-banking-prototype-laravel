<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1a1a2e;
            margin: 0;
            padding: 40px;
            font-size: 12px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #16213e;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 24px;
            color: #16213e;
            margin: 0 0 5px;
        }
        .header .subtitle {
            font-size: 14px;
            color: #0f3460;
        }
        .header .shield {
            font-size: 11px;
            color: #53599a;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            font-size: 14px;
            color: #16213e;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .field {
            margin-bottom: 8px;
        }
        .field .label {
            font-weight: bold;
            color: #0f3460;
            display: inline-block;
            min-width: 160px;
        }
        .field .value {
            color: #333;
        }
        .status-active {
            color: #27ae60;
            font-weight: bold;
        }
        .status-revoked, .status-expired {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-suspended, .status-pending {
            color: #f39c12;
            font-weight: bold;
        }
        .fingerprint {
            font-family: monospace;
            font-size: 10px;
            word-break: break-all;
            background: #f5f5f5;
            padding: 8px;
            border-radius: 4px;
        }
        .disclaimer {
            margin-top: 40px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #16213e;
            font-size: 10px;
            color: #666;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #e0e0e0;
            padding-top: 15px;
        }
        .verification {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        .verification h3 {
            margin: 0 0 10px;
            font-size: 12px;
            color: #16213e;
        }
        .verification .url {
            font-family: monospace;
            font-size: 9px;
            word-break: break-all;
            color: #0f3460;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $branding['name'] ?? config('brand.name', 'Zelta') . '' }} Certificate</h1>
        <div class="subtitle">Digital Trust Certificate</div>
        <div class="shield">Secured by {{ $branding['shield'] ?? 'Aegis Shield' }}</div>
    </div>

    <div class="section">
        <h2>Certificate Details</h2>
        <div class="field">
            <span class="label">Certificate ID:</span>
            <span class="value">{{ $certificate->certificateId }}</span>
        </div>
        <div class="field">
            <span class="label">Subject:</span>
            <span class="value">{{ $certificate->subjectId }}</span>
        </div>
        <div class="field">
            <span class="label">Status:</span>
            <span class="value status-{{ strtolower($certificate->status->value) }}">
                {{ strtoupper($certificate->status->value) }}
            </span>
        </div>
        <div class="field">
            <span class="label">Root Certificate:</span>
            <span class="value">{{ $certificate->isRootCertificate() ? 'Yes' : 'No' }}</span>
        </div>
    </div>

    <div class="section">
        <h2>Validity Period</h2>
        <div class="field">
            <span class="label">Valid From:</span>
            <span class="value">{{ $certificate->validFrom->format('Y-m-d H:i:s T') }}</span>
        </div>
        <div class="field">
            <span class="label">Valid Until:</span>
            <span class="value">{{ $certificate->validUntil->format('Y-m-d H:i:s T') }}</span>
        </div>
        <div class="field">
            <span class="label">Currently Valid:</span>
            <span class="value">{{ $certificate->isValid() ? 'Yes' : 'No' }}</span>
        </div>
    </div>

    <div class="section">
        <h2>Certificate Fingerprint</h2>
        <div class="fingerprint">{{ $certificate->getFingerprint() }}</div>
    </div>

    @if($verificationUrl)
    <div class="verification">
        <h3>Verification</h3>
        <p>Scan or visit the following URL to verify this certificate:</p>
        <div class="url">{{ $verificationUrl }}</div>
        @if($deepLink)
        <p style="margin-top: 8px; font-size: 10px;">
            Deep Link: <span class="url">{{ $deepLink }}</span>
        </p>
        @endif
    </div>
    @endif

    <div class="disclaimer">
        <strong>Disclaimer:</strong> {{ $disclaimer }}
    </div>

    <div class="footer">
        <p>Generated on {{ now()->format('Y-m-d H:i:s T') }}</p>
        <p>{{ $branding['name'] ?? config('brand.name', 'Zelta') . '' }} &mdash; Digital Trust Infrastructure</p>
    </div>
</body>
</html>
