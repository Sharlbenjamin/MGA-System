# Git Repository Error: "fatal: not a git repository" - Investigation & Fix

## Problem
You're experiencing the error: `fatal: not a git repository (or any of the parent directories): .git` when running `git push` or `git pull`.

## Root Cause Identified

The environment variable `GIT_DISCOVERY_ACROSS_FILESYSTEM=0` is set in your environment. This prevents Git from searching parent directories for a `.git` folder.

### What This Means
- **Normal behavior**: Git searches the current directory and all parent directories until it finds a `.git` folder
- **With `GIT_DISCOVERY_ACROSS_FILESYSTEM=0`**: Git only looks in the current directory
- **Result**: If you run git commands from a subdirectory, Git won't find the repository

## When This Error Occurs

1. **Running git commands from a subdirectory** (e.g., `cd app/Models && git status`)
2. **Scripts that change directories** before running git commands
3. **After running scripts** that modify the working directory

## Solutions

### Solution 1: Unset the Environment Variable (Recommended)

**Temporary fix** (current session only):
```bash
unset GIT_DISCOVERY_ACROSS_FILESYSTEM
```

**Permanent fix** (add to your shell profile):
```bash
# Add to ~/.bashrc or ~/.bash_profile
unset GIT_DISCOVERY_ACROSS_FILESYSTEM
```

Then reload your shell:
```bash
source ~/.bashrc
# or
source ~/.bash_profile
```

### Solution 2: Always Run Git Commands from Repository Root

Before running git commands, ensure you're in the repository root:
```bash
cd /workspace  # or wherever your .git folder is
git push
git pull
```

### Solution 3: Use Git's Built-in Path Resolution

Git can find the repository root automatically:
```bash
# From any subdirectory, get to the repo root
cd $(git rev-parse --show-toplevel)
```

### Solution 4: Create a Git Wrapper Script

Use the provided `git-safe.sh` script (see below) that ensures you're always in the correct directory.

## Scripts That May Cause Issues

The following scripts in your repository change directories and may trigger this error:

1. **`fix-livewire-server.sh`** - Changes to `/home/1417710.cloudwaysapps.com/fdcpgwbqxd/public_html`
2. **`apply-livewire-patch.sh`** - Changes to `/home/1417710.cloudwaysapps.com/fdcpgwbqxd/public_html`

If these scripts run git commands after changing directories, they will fail.

## Diagnostic Tool

Run the diagnostic script to check your current setup:
```bash
./diagnose-git-error.sh
```

This will show:
- Current working directory
- Whether `.git` exists
- Git environment variables
- Test git commands
- Scripts that change directories

## Prevention

1. **Always check your directory** before running git commands:
   ```bash
   pwd
   git status  # This will fail if not in a git repo
   ```

2. **Use the diagnostic script** when you encounter the error:
   ```bash
   ./diagnose-git-error.sh
   ```

3. **Add directory checks to scripts** that run git commands:
   ```bash
   # At the start of scripts that use git
   if ! git rev-parse --git-dir > /dev/null 2>&1; then
       echo "Error: Not in a git repository"
       exit 1
   fi
   ```

## Quick Fix Commands

```bash
# 1. Unset the problematic environment variable
unset GIT_DISCOVERY_ACROSS_FILESYSTEM

# 2. Navigate to repository root
cd $(git rev-parse --show-toplevel)

# 3. Verify you're in a git repository
git status

# 4. Now you can push/pull safely
git push
git pull
```

## Additional Notes

- The `GIT_DISCOVERY_ACROSS_FILESYSTEM` variable is sometimes set by CI/CD systems or deployment scripts
- It's generally safe to unset this variable unless you have a specific reason to keep it
- If you need to keep it set, always ensure you're in the repository root before running git commands
