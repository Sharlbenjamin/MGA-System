#!/bin/bash

# Git Repository Error Diagnostic Script
# This script helps track down the "fatal: not a git repository" error

echo "=========================================="
echo "Git Repository Error Diagnostic Tool"
echo "=========================================="
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 1. Check current directory
echo "1. Current Working Directory:"
print_info "PWD: $(pwd)"
echo ""

# 2. Check if .git exists in current directory
echo "2. Git Directory Check:"
if [ -d ".git" ]; then
    print_success ".git directory exists in current directory"
    ls -ld .git
else
    print_error ".git directory NOT found in current directory"
fi
echo ""

# 3. Check if .git exists in parent directories
echo "3. Parent Directory Git Check:"
CURRENT_DIR=$(pwd)
FOUND_GIT=false
while [ "$CURRENT_DIR" != "/" ]; do
    if [ -d "$CURRENT_DIR/.git" ]; then
        print_success "Found .git in: $CURRENT_DIR"
        FOUND_GIT=true
        break
    fi
    CURRENT_DIR=$(dirname "$CURRENT_DIR")
done
if [ "$FOUND_GIT" = false ]; then
    print_error "No .git directory found in current or parent directories"
fi
echo ""

# 4. Check Git environment variables
echo "4. Git Environment Variables:"
if [ -n "$GIT_DIR" ]; then
    print_warning "GIT_DIR is set to: $GIT_DIR"
    if [ ! -d "$GIT_DIR" ]; then
        print_error "GIT_DIR points to non-existent directory!"
    fi
else
    print_success "GIT_DIR is not set (normal)"
fi

if [ -n "$GIT_WORK_TREE" ]; then
    print_warning "GIT_WORK_TREE is set to: $GIT_WORK_TREE"
else
    print_success "GIT_WORK_TREE is not set (normal)"
fi

GIT_DISCOVERY=$(env | grep GIT_DISCOVERY_ACROSS_FILESYSTEM || echo "")
if [ -n "$GIT_DISCOVERY" ]; then
    print_warning "$GIT_DISCOVERY"
    print_warning "This prevents Git from searching parent directories!"
else
    print_success "GIT_DISCOVERY_ACROSS_FILESYSTEM is not set"
fi
echo ""

# 5. Test git commands
echo "5. Testing Git Commands:"
print_info "Testing: git rev-parse --show-toplevel"
if git rev-parse --show-toplevel 2>/dev/null; then
    print_success "Git repository detected"
    REPO_ROOT=$(git rev-parse --show-toplevel)
    print_info "Repository root: $REPO_ROOT"
else
    print_error "Git repository NOT detected from current directory"
fi
echo ""

print_info "Testing: git rev-parse --git-dir"
if git rev-parse --git-dir 2>/dev/null; then
    GIT_DIR_PATH=$(git rev-parse --git-dir)
    print_success "Git directory: $GIT_DIR_PATH"
else
    print_error "Cannot determine git directory"
fi
echo ""

print_info "Testing: git status"
if git status >/dev/null 2>&1; then
    print_success "git status works"
else
    ERROR=$(git status 2>&1)
    print_error "git status failed: $ERROR"
fi
echo ""

# 6. Check git aliases
echo "6. Git Aliases:"
ALIASES=$(git config --get-regexp alias 2>/dev/null)
if [ -n "$ALIASES" ]; then
    print_info "Found git aliases:"
    echo "$ALIASES"
else
    print_success "No git aliases found"
fi
echo ""

# 7. Check if we're in a submodule
echo "7. Submodule Check:"
if [ -f ".git" ] && ! [ -d ".git" ]; then
    print_warning "Current directory appears to be a git submodule (.git is a file)"
    cat .git
else
    print_success "Not a submodule"
fi
echo ""

# 8. Check for scripts that might change directories
echo "8. Checking for scripts that change directories:"
SCRIPTS=$(find . -maxdepth 1 -name "*.sh" -type f 2>/dev/null)
if [ -n "$SCRIPTS" ]; then
    print_info "Found shell scripts in root:"
    for script in $SCRIPTS; do
        if grep -q "cd " "$script" 2>/dev/null; then
            print_warning "$script contains 'cd' commands:"
            grep -n "cd " "$script" | head -5
        fi
    done
else
    print_success "No shell scripts found in root"
fi
echo ""

# 9. Summary and recommendations
echo "=========================================="
echo "Summary and Recommendations:"
echo "=========================================="
echo ""

if [ ! -d ".git" ] && [ "$FOUND_GIT" = false ]; then
    print_error "CRITICAL: No git repository found!"
    echo "  → Make sure you're in the correct directory"
    echo "  → Run: cd /workspace (or your project root)"
elif [ ! -d ".git" ] && [ "$FOUND_GIT" = true ]; then
    print_warning "You're in a subdirectory of a git repository"
    echo "  → Run: cd $(git rev-parse --show-toplevel 2>/dev/null || echo 'repository root')"
fi

if [ -n "$GIT_DISCOVERY" ]; then
    print_warning "GIT_DISCOVERY_ACROSS_FILESYSTEM is set"
    echo "  → This prevents Git from finding parent .git directories"
    echo "  → If you need to run git from subdirectories, consider unsetting it:"
    echo "    unset GIT_DISCOVERY_ACROSS_FILESYSTEM"
fi

echo ""
print_info "To track when this error occurs, run this script before/after git commands"
print_info "Or add logging to your git commands:"
echo "  git push 2>&1 | tee git-push.log"
echo "  git pull 2>&1 | tee git-pull.log"
