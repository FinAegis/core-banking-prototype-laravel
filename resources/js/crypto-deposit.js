import QRCode from 'qrcode-generator';

window.selectCrypto = function(crypto) {
    // Remove active state from all options
    document.querySelectorAll('.crypto-option').forEach(el => {
        el.classList.remove('border-blue-500');
    });
    
    // Add active state to selected option
    event.target.closest('.crypto-option').classList.add('border-blue-500');
    
    // Show deposit details
    document.getElementById('depositDetails').classList.remove('hidden');
    
    // Update crypto-specific details
    const addresses = {
        'BTC': '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
        'ETH': '0x742d35Cc6634C0532925a3b844Bc9e7595f06789',
        'USDT': 'TN3W4H6rK2UM6GnKms9iFGQfVY73Gmwm7T'
    };
    
    const minDeposits = {
        'BTC': '0.001 BTC',
        'ETH': '0.01 ETH',
        'USDT': '10 USDT'
    };
    
    const confirmations = {
        'BTC': '3',
        'ETH': '12',
        'USDT': '20'
    };
    
    const address = addresses[crypto];
    document.getElementById('cryptoAddress').value = address;
    document.getElementById('selectedCrypto').textContent = crypto;
    document.getElementById('minDeposit').textContent = minDeposits[crypto];
    document.getElementById('confirmations').textContent = confirmations[crypto];
    
    // Generate QR code
    generateQRCode(address, crypto);
}

window.generateQRCode = function(address, crypto) {
    const qrcodeContainer = document.getElementById('qrcode');
    qrcodeContainer.innerHTML = ''; // Clear existing content
    
    try {
        // Create QR code
        const qr = QRCode(0, 'H'); // 0 = auto version, H = high error correction
        qr.addData(address);
        qr.make();
        
        // Create image
        const img = document.createElement('img');
        img.src = qr.createDataURL(8, 0); // 8 = cell size, 0 = margin
        img.style.width = '200px';
        img.style.height = '200px';
        img.style.display = 'block';
        img.alt = `QR code for ${crypto} address`;
        
        qrcodeContainer.appendChild(img);
        
        // Add address text below QR code
        const addressText = document.createElement('div');
        addressText.className = 'mt-2 text-xs text-gray-600 dark:text-gray-400 break-all text-center max-w-[200px]';
        addressText.textContent = address;
        qrcodeContainer.appendChild(addressText);
    } catch (error) {
        console.error('QR Code generation error:', error);
        qrcodeContainer.innerHTML = '<div class="text-red-500 text-sm">Error generating QR code</div>';
    }
}

window.copyAddress = function() {
    const addressInput = document.getElementById('cryptoAddress');
    
    // Use modern clipboard API if available
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(addressInput.value).then(() => {
            showCopyFeedback();
        }).catch(() => {
            fallbackCopy();
        });
    } else {
        fallbackCopy();
    }
}

window.fallbackCopy = function() {
    const addressInput = document.getElementById('cryptoAddress');
    addressInput.select();
    addressInput.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        showCopyFeedback();
    } catch (err) {
        console.error('Failed to copy:', err);
    }
}

window.showCopyFeedback = function() {
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Copied!';
    button.classList.add('bg-green-600', 'hover:bg-green-700');
    button.classList.remove('bg-blue-600', 'hover:bg-blue-700');
    
    setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove('bg-green-600', 'hover:bg-green-700');
        button.classList.add('bg-blue-600', 'hover:bg-blue-700');
    }, 2000);
}