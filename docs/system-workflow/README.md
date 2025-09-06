# System Workflow Documentation

This directory contains the comprehensive system workflow documentation for the MGA System.

## Files

- **`System Workflow.md`** - The main comprehensive documentation file
- **`manifest.json`** - Tracks file changes and generation metadata
- **`README.md`** - This file

## Auto-Update System

The system workflow documentation is automatically generated and maintained using:

### 1. Artisan Command
```bash
php artisan system:generate-workflow
```

### 2. Git Pre-commit Hook
Automatically runs when committing changes to ensure documentation stays up-to-date.

### 3. GitHub Action
Automatically updates documentation on push to main/develop branches and creates PR comments.

## Manual Generation

To manually generate the documentation:

```bash
# Generate documentation
php artisan system:generate-workflow

# Force regeneration (ignore change detection)
php artisan system:generate-workflow --force
```

## Monitored Files

The system monitors the following file patterns for changes:

- `app/Models/*.php` - Eloquent models
- `app/Filament/Resources/**/*.php` - Filament resources
- `app/Filament/Pages/**/*.php` - Filament pages
- `app/Filament/Widgets/**/*.php` - Filament widgets
- `app/Http/Controllers/*.php` - HTTP controllers
- `app/Mail/*.php` - Mail classes
- `app/Console/Commands/*.php` - Console commands
- `app/Services/*.php` - Service classes
- `app/Policies/*.php` - Policy classes
- `database/migrations/*.php` - Database migrations
- `routes/*.php` - Route definitions
- `config/*.php` - Configuration files
- `composer.json` - Composer dependencies
- `composer.lock` - Locked dependencies
- `package.json` - NPM dependencies
- `resources/views/**/*.blade.php` - Blade templates

## Documentation Structure

The generated documentation includes:

1. **Project Overview** - System purpose and module map
2. **Versions & Environment** - Framework versions and dependencies
3. **Database Schema** - Complete database structure
4. **Eloquent Models** - Model definitions and relationships
5. **Filament Resources** - Admin panel configuration
6. **Routing & Controllers** - API and web routes
7. **Domain Workflows** - Business process diagrams
8. **Jobs, Events, Listeners** - Background processing
9. **Policies & Permissions** - Access control
10. **Services & Integrations** - External integrations
11. **Configuration Highlights** - Key configuration changes
12. **Testing** - Test structure and coverage
13. **Known Limitations & TODOs** - Current limitations and future work

## Troubleshooting

### Documentation Not Updating
1. Check if the generator script exists: `scripts/generate_system_workflow.php`
2. Verify the Artisan command is registered: `php artisan list | grep system:generate-workflow`
3. Check file permissions on the documentation directory
4. Review the manifest.json for file change tracking

### Git Hook Issues
1. Ensure the pre-commit hook is executable: `chmod +x .git/hooks/pre-commit`
2. Check if the hook is properly installed in the repository
3. Verify the hook script syntax

### GitHub Action Issues
1. Check the workflow file: `.github/workflows/update-system-workflow.yml`
2. Verify the GITHUB_TOKEN has necessary permissions
3. Review the action logs for specific error messages

## Contributing

When making changes to the codebase:

1. The documentation will be automatically updated via the pre-commit hook
2. If manual updates are needed, run `php artisan system:generate-workflow`
3. The GitHub Action will handle updates on the main branch
4. For PRs, the action will comment if documentation changes are detected

## Version History

- **v1.0.0** - Initial implementation with basic file monitoring and documentation generation
