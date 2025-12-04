# How to Clear Cache

## Quick Methods (Try These First)

### 1. Browser Cache
- **Chrome/Edge**: Press `Ctrl+Shift+Delete` (Windows) or `Cmd+Shift+Delete` (Mac)
- **Firefox**: Press `Ctrl+Shift+Delete` (Windows) or `Cmd+Shift+Delete` (Mac)
- **Safari**: Press `Cmd+Option+E` to empty caches
- **Hard Refresh**: Press `Ctrl+F5` (Windows) or `Cmd+Shift+R` (Mac) on the page

### 2. WordPress Admin Cache Clear
1. Go to WordPress Admin Dashboard
2. Look for cache plugin menu (WP Rocket, W3 Total Cache, WP Super Cache, etc.)
3. Click "Clear Cache" or "Purge Cache"

### 3. DDEV Cache Clear (if using DDEV)
```bash
ddev restart
```

## Advanced Methods

### Clear All WordPress Caches via WP-CLI
```bash
cd /Users/ralph/projects/maloney-affordable
wp cache flush
wp transient delete --all
```

### Clear Specific Cache Types

#### Object Cache
```bash
wp cache flush
```

#### Transients
```bash
wp transient delete --all
```

#### Divi Cache
- Go to Divi → Theme Options → Builder → Advanced
- Click "Clear Static CSS Cache"

#### Toolset Cache
- Go to Toolset → Settings
- Look for cache clearing options

### Manual Cache Clearing

#### Delete Cache Files
```bash
# Clear WordPress cache directory
rm -rf wp-content/cache/*

# Clear Divi cache
rm -rf wp-content/et-cache/*

# Clear any plugin caches
rm -rf wp-content/cache/plugins/*
```

#### Clear Browser Cache for Specific Site
1. Open Developer Tools (F12)
2. Right-click the refresh button
3. Select "Empty Cache and Hard Reload"

### Server-Side Cache (if on WP Engine, Kinsta, etc.)
- Log into your hosting control panel
- Look for "Clear Cache" or "Purge Cache" option
- Clear all caches

## For JavaScript/CSS Changes Specifically

Since you're seeing JavaScript errors, try:

1. **Hard refresh the browser** (most important)
   - Windows: `Ctrl + F5`
   - Mac: `Cmd + Shift + R`

2. **Clear browser cache for the site**
   - Open DevTools (F12)
   - Right-click refresh button → "Empty Cache and Hard Reload"

3. **Disable cache in DevTools** (while testing)
   - Open DevTools (F12)
   - Go to Network tab
   - Check "Disable cache" checkbox
   - Keep DevTools open while testing

4. **Clear WordPress transients**
   ```bash
   wp transient delete --all
   ```

5. **Restart DDEV** (if using DDEV)
   ```bash
   ddev restart
   ```

## Quick Command to Clear Everything

```bash
cd /Users/ralph/projects/maloney-affordable
wp cache flush
wp transient delete --all
rm -rf wp-content/cache/*
rm -rf wp-content/et-cache/*
ddev restart
```

Then hard refresh your browser (`Cmd+Shift+R` or `Ctrl+F5`).

