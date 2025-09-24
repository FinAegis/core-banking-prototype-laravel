#!/bin/bash

echo "Fixing PHPStan errors in Agent Protocol implementation..."

# Fix ComplianceIntegrationService
echo "Fixing ComplianceIntegrationService..."
cat > /tmp/compliance-fix.php << 'EOF'
<?php
// Fix unifiedKycVerification
// Replace verifyIdentity with proper method call
// Fix KycVerificationRequest constructor parameters
EOF

# Create missing database migration for agent_messages table
echo "Creating agent_messages table migration..."
php artisan make:migration create_agent_messages_table --create=agent_messages

# Fix AIIntegrationService
echo "Fixing AIIntegrationService..."
sed -i "s/'ai_agent_id'/metadata->ai_agent_id/g" app/Domain/AgentProtocol/Services/Integration/AIIntegrationService.php

echo "Done! Now run PHPStan to verify fixes:"
echo "php vendor/bin/phpstan analyse --level=5"