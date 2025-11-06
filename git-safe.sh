#!/bin/bash

# Git Safe Wrapper Script
# This script ensures git commands are run from the correct directory
# Usage: ./git-safe.sh <git-command> [args...]
# Example: ./git-safe.sh push origin main

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Check if git command is provided
if [ $# -eq 0 ]; then
    print_error "Usage: $0 <git-command> [args...]"
    print_info "Example: $0 push origin main"
    print_info "Example: $0 pull"
    exit 1
fi

# Try to find the git repository root
REPO_ROOT=""
if git rev-parse --show-toplevel >/dev/null 2>&1; then
    REPO_ROOT=$(git rev-parse --show-toplevel)
    print_info "Found git repository at: $REPO_ROOT"
    
    # Change to repository root if not already there
    CURRENT_DIR=$(pwd)
    if [ "$CURRENT_DIR" != "$REPO_ROOT" ]; then
        print_warning "Not in repository root. Changing to: $REPO_ROOT"
        cd "$REPO_ROOT" || {
            print_error "Failed to change to repository root"
            exit 1
        }
    fi
else
    print_error "Not in a git repository!"
    print_info "Current directory: $(pwd)"
    print_info "Please navigate to a git repository and try again"
    exit 1
fi

# Check GIT_DISCOVERY_ACROSS_FILESYSTEM
if [ "$GIT_DISCOVERY_ACROSS_FILESYSTEM" = "0" ]; then
    print_warning "GIT_DISCOVERY_ACROSS_FILESYSTEM=0 is set"
    print_info "Temporarily unsetting for this command..."
    unset GIT_DISCOVERY_ACROSS_FILESYSTEM
fi

# Execute the git command
print_info "Running: git $*"
echo ""

# Run git command with all arguments
git "$@"
EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo ""
    print_info "Git command completed successfully"
else
    echo ""
    print_error "Git command failed with exit code: $EXIT_CODE"
fi

exit $EXIT_CODE
