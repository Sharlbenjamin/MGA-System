# Backup: Old file view templates

This folder keeps the **old standalone compact view** (the one that used `x-app-layout` and required Vite).

- **file-compact-standalone-old.blade.php** – Full page with app layout (uses Vite).
- **_file-compact-content-old.blade.php** – Compact content partial used by the above.

To restore the old standalone view:

1. Copy `file-compact-standalone-old.blade.php` to `../file-compact-standalone.blade.php`.
2. Copy `_file-compact-content-old.blade.php` to `../_file-compact-content.blade.php`.
3. In `FileCompactViewController::show()`, return the view again instead of redirecting:
   `return view('filament.pages.files.file-compact-standalone', [...]);`
4. Ensure Vite manifest exists: run `npm run build` in production, or use `npm run dev` in development.

The **classic Overview** (3-column + Current Text) is backed up in:
`app/Filament/Resources/FileResource/Pages/old/ViewFileClassicBackup.php`

To restore the classic Overview in the Filament file view, copy the Overview schema from that file into `ViewFile.php`’s `infolist()` method.
