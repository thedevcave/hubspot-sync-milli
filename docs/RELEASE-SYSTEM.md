---
layout: page
title: Release System
nav_order: 8
---

# HubSpot Sync Milli - Release & Update System

This plugin includes an automatic update system that checks for new releases on GitHub and allows users to update directly from their WordPress admin.

## How It Works

### For Maintainers

1. **Create a New Release**: 
   - Tag your release with semantic versioning (e.g., `v1.0.1`, `v1.1.0`)
   - Create a GitHub release with release notes
   - The GitHub Action will automatically create and attach `hubspot-sync-milli.zip` to the release

2. **Release Process**:
   ```bash
   # Update version in hubspot-sync-milli.php
   # Commit your changes
   git tag v1.0.1
   git push origin v1.0.1
   # Create release on GitHub with this tag
   ```

3. **GitHub Action**:
   - Runs automatically when a release is published
   - Installs PHP dependencies via Composer
   - Creates a clean zip using `.gitattributes` export rules
   - Includes necessary files, excludes development files
   - Attaches the zip to the GitHub release

### For End Users

1. **Automatic Update Checks**:
   - Plugin checks for updates every 12 hours
   - Checks are cached to avoid API rate limits
   - Updates appear in WordPress admin under Plugins

2. **Manual Updates**:
   - Users can check for updates from Plugins page
   - Standard WordPress update flow
   - No additional configuration required

## File Structure

```
hubspot-sync-milli/
├── .gitattributes          # Controls which files are included in releases
├── .github/
│   └── workflows/
│       └── release.yml     # GitHub Action for creating release packages
├── includes/
│   ├── class-updater.php   # Update checker class
│   └── ...
├── hubspot-sync-milli.php  # Main plugin file (update version here)
└── ...
```

## Configuration

### Version Management

Update the version in two places when releasing:

1. `hubspot-sync-milli.php`:
   ```php
   * Version: 1.0.1
   define( 'HUBSPOT_SYNC_MILLI_VERSION', '1.0.1' );
   ```

2. Git tag: `v1.0.1`

### GitHub Repository Settings

- Repository: `https://github.com/thedevcave/hubspot-sync-milli`
- Releases are checked via GitHub API
- No authentication required for public repositories

### Update Frequency

- Checks cached for 12 hours
- Cache key: `hubspot_sync_milli_latest_release`
- Manual refresh available via WordPress plugins page

## Troubleshooting

### Debug Logging

Enable WordPress debug logging to see update checker activity:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Look for logs prefixed with `[HubSpot Sync - Milli Updater]`.

### Common Issues

1. **Updates not appearing**:
   - Check GitHub release has `hubspot-sync-milli.zip` asset
   - Verify version number format (semantic versioning)
   - Clear update cache: delete `hubspot_sync_milli_latest_release` transient

2. **Download fails**:
   - Verify zip file exists in GitHub release assets
   - Check WordPress can access GitHub URLs
   - Review error logs for specific issues

### Manual Cache Clear

```php
delete_transient( 'hubspot_sync_milli_latest_release' );
```

## Security Notes

- Uses WordPress HTTP API for secure requests
- Validates response codes and data structure
- No credentials stored or transmitted
- Standard WordPress update mechanisms