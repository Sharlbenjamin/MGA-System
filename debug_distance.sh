#!/bin/bash

echo "=== MGA System Distance Calculation Debug Script ==="
echo "This script will help debug the distance calculation issue"
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from your Laravel project root."
    exit 1
fi

echo "✅ Laravel project detected"
echo ""

# Option 1: Run the Laravel command
echo "1. Running Laravel debug command..."
php artisan debug:distance 121 --branch-id=58
echo ""

# Option 2: Run the standalone script
echo "2. Running standalone debug script..."
php debug_live_server.php
echo ""

# Option 3: Check logs
echo "3. Checking recent logs for distance calculation errors..."
echo "Last 20 lines of Laravel log:"
tail -20 storage/logs/laravel.log | grep -i "distance\|google\|api" || echo "No distance-related log entries found"
echo ""

# Option 4: Check environment
echo "4. Checking environment configuration..."
if [ -f ".env" ]; then
    echo "✅ .env file exists"
    if grep -q "GOOGLE_MAPS_API_KEY" .env; then
        echo "✅ GOOGLE_MAPS_API_KEY is set in .env"
        echo "   Key preview: $(grep GOOGLE_MAPS_API_KEY .env | cut -d'=' -f2 | cut -c1-10)..."
    else
        echo "❌ GOOGLE_MAPS_API_KEY not found in .env"
    fi
else
    echo "❌ .env file not found"
fi
echo ""

echo "=== Debug Complete ==="
echo "Please share the output above to help identify the issue." 