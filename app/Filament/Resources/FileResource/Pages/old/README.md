# Backup: Classic file view (Overview tab)

The **classic Overview** (3-column layout + Current Text) was the original Filament file view content.

- It was removed from `ViewFile.php` so the file view now always shows the **compact view** (single ViewEntry using `view-file-compact.blade.php`).
- To restore the classic Overview: get the previous infolist Overview schema from git history, e.g.:
  ```bash
  git log -p -- app/Filament/Resources/FileResource/Pages/ViewFile.php
  ```
  Then copy the classic branch (the array of `InfolistSection::make()->columns(3)->schema([...])` and the "Current Text" `InfolistSection`) back into `ViewFile.php`’s `infolist()` as the Overview tab schema (or reintroduce a toggle and use it as the classic branch).

The **standalone compact view** (app layout, Vite) is backed up in:
`resources/views/filament/pages/files/old/`
- `file-compact-standalone-old.blade.php`
- `_file-compact-content-old.blade.php`
- See that folder’s README for how to restore it.
