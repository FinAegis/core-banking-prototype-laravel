#!/bin/bash

# Script to run CI tests locally
set -e

echo "🔧 Setting up CI test environment..."

# Ensure coverage mode is enabled
export XDEBUG_MODE=coverage

# Use CI configuration
export APP_ENV=testing

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

echo ""
echo "🧪 Running Unit Tests..."
./vendor/bin/pest --testsuite=Unit --configuration=phpunit.ci.xml --exclude-group=slow || {
    echo "❌ Unit tests failed"
    exit 1
}

echo ""
echo "🔐 Running Security Tests..."
./vendor/bin/pest --testsuite=Security --configuration=phpunit.ci.xml || {
    echo "❌ Security tests failed"
    exit 1
}

echo ""
echo "🎯 Running Feature Tests..."
./vendor/bin/pest --testsuite=Feature --configuration=phpunit.ci.xml --exclude-group=slow || {
    echo "❌ Feature tests failed"
    exit 1
}

echo ""
echo "🔗 Running Integration Tests..."
./vendor/bin/pest --testsuite=Integration --configuration=phpunit.ci.xml --exclude-group=slow || {
    echo "❌ Integration tests failed"
    exit 1
}

echo ""
echo "✅ All tests passed!"