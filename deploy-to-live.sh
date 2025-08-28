#!/bin/bash

# =============================================================================
# MGA System - Staging to Live Deployment Script
# =============================================================================
# This script deploys all changes from staging branch to live environment
# Created: $(date)
# =============================================================================

echo "🚀 Starting MGA System Deployment to Live Environment..."
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check prerequisites
print_status "Checking prerequisites..."

if ! command_exists php; then
    print_error "PHP is not installed or not in PATH"
    exit 1
fi

if ! command_exists composer; then
    print_error "Composer is not installed or not in PATH"
    exit 1
fi

if ! command_exists git; then
    print_error "Git is not installed or not in PATH"
    exit 1
fi

print_success "All prerequisites are met"

# =============================================================================
# STEP 1: Backup Current State
# =============================================================================
print_status "Step 1: Creating backup of current state..."

# Create backup directory with timestamp
BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup current database (if possible)
if command_exists mysqldump; then
    print_status "Creating database backup..."
    # Note: You'll need to update these credentials for your live environment
    # mysqldump -u [username] -p [database_name] > "$BACKUP_DIR/database_backup.sql"
    print_warning "Database backup skipped - update credentials in script if needed"
else
    print_warning "mysqldump not available - skipping database backup"
fi

# Backup current .env file
if [ -f ".env" ]; then
    cp .env "$BACKUP_DIR/.env.backup"
    print_success "Environment file backed up"
fi

print_success "Backup completed in: $BACKUP_DIR"

# =============================================================================
# STEP 2: Switch to Live Branch
# =============================================================================
print_status "Step 2: Switching to live branch..."

# Check current branch
CURRENT_BRANCH=$(git branch --show-current)
print_status "Current branch: $CURRENT_BRANCH"

if [ "$CURRENT_BRANCH" != "main" ]; then
    print_status "Switching to main branch..."
    git checkout main
    if [ $? -ne 0 ]; then
        print_error "Failed to switch to main branch"
        exit 1
    fi
fi

# Pull latest changes from main
print_status "Pulling latest changes from main..."
git pull origin main
if [ $? -ne 0 ]; then
    print_error "Failed to pull latest changes"
    exit 1
fi

print_success "Successfully switched to main branch"

# =============================================================================
# STEP 3: Merge Staging Changes
# =============================================================================
print_status "Step 3: Merging staging changes..."

# Check if staging branch exists
if ! git show-ref --verify --quiet refs/heads/staging; then
    print_error "Staging branch does not exist"
    exit 1
fi

# Merge staging into main
print_status "Merging staging branch into main..."
git merge staging --no-ff -m "Merge staging into main - $(date)"
if [ $? -ne 0 ]; then
    print_error "Merge failed - please resolve conflicts manually"
    print_status "You can abort the merge with: git merge --abort"
    exit 1
fi

print_success "Successfully merged staging changes"

# =============================================================================
# STEP 4: Update Dependencies
# =============================================================================
print_status "Step 4: Updating dependencies..."

# Install/update Composer dependencies
print_status "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader
if [ $? -ne 0 ]; then
    print_error "Composer install failed"
    exit 1
fi

print_success "Dependencies updated successfully"

# =============================================================================
# STEP 5: Database Migrations
# =============================================================================
print_status "Step 5: Running database migrations..."

# Check migration status
print_status "Checking migration status..."
php artisan migrate:status

# Run migrations
print_status "Running migrations..."
php artisan migrate --force
if [ $? -ne 0 ]; then
    print_error "Migration failed"
    print_status "You can check the migration status with: php artisan migrate:status"
    exit 1
fi

print_success "Database migrations completed successfully"

# =============================================================================
# STEP 6: Clear All Caches
# =============================================================================
print_status "Step 6: Clearing all caches..."

# Clear all Laravel caches
print_status "Clearing Laravel caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Clear Filament caches
print_status "Clearing Filament caches..."
php artisan filament:cache-components

# Clear all caches with optimize:clear
print_status "Running comprehensive cache clear..."
php artisan optimize:clear

print_success "All caches cleared successfully"

# =============================================================================
# STEP 7: Verify Deployment
# =============================================================================
print_status "Step 7: Verifying deployment..."

# Check if Laravel is working
print_status "Checking Laravel application..."
php artisan --version
if [ $? -ne 0 ]; then
    print_error "Laravel application check failed"
    exit 1
fi

# Check database connection
print_status "Checking database connection..."
php artisan tinker --execute="echo 'Database connection: ' . (DB::connection()->getPdo() ? 'OK' : 'FAILED') . PHP_EOL;"
if [ $? -ne 0 ]; then
    print_warning "Database connection check failed - please verify manually"
fi

print_success "Deployment verification completed"

# =============================================================================
# STEP 8: Post-Deployment Tasks
# =============================================================================
print_status "Step 8: Running post-deployment tasks..."

# Set proper permissions (if needed)
print_status "Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs

# Generate application key if not exists
if [ -z "$(grep 'APP_KEY=' .env | cut -d '=' -f2)" ] || [ "$(grep 'APP_KEY=' .env | cut -d '=' -f2)" = "" ]; then
    print_status "Generating application key..."
    php artisan key:generate
fi

# Fix branch contact data if needed
print_status "Checking and fixing branch contact data..."
php artisan branches:check-status
if [ $? -eq 0 ]; then
    read -p "Do you want to fix branch contact data? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_status "Fixing branch contact data..."
        php artisan branches:fix-contacts
        if [ $? -eq 0 ]; then
            print_success "Branch contact data fixed successfully"
        else
            print_warning "Branch contact data fix had issues - check manually"
        fi
    fi
fi

print_success "Post-deployment tasks completed"

# =============================================================================
# STEP 9: Final Verification
# =============================================================================
print_status "Step 9: Final verification checklist..."

echo ""
echo "✅ Deployment completed successfully!"
echo ""
echo "📋 Manual verification checklist:"
echo "   1. Check Request Appointments page functionality"
echo "   2. Verify provider branch contact fields are working"
echo "   3. Test email sending functionality"
echo "   4. Check that phone functionality is removed from contact info"
echo "   5. Verify city filter shows cities from file's country"
echo "   6. Test appointment request sending"
echo ""
echo "🔧 If you encounter issues:"
echo "   - Check logs: tail -f storage/logs/laravel.log"
echo "   - Clear caches again: php artisan optimize:clear"
echo "   - Rollback migration if needed: php artisan migrate:rollback --step=1"
echo ""
echo "📁 Backup location: $BACKUP_DIR"
echo ""

print_success "Deployment script completed successfully! 🎉"

# =============================================================================
# OPTIONAL: Push to Remote Repository
# =============================================================================
read -p "Do you want to push the merged changes to the remote repository? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_status "Pushing changes to remote repository..."
    git push origin main
    if [ $? -eq 0 ]; then
        print_success "Changes pushed to remote repository"
    else
        print_error "Failed to push changes to remote repository"
    fi
fi

echo ""
print_success "Deployment process completed! 🚀"
