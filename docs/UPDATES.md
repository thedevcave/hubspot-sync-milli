# Plugin Updates

The HubSpot Sync - Milli plugin automatically checks for updates from the public GitHub repository.

## How It Works

- Updates are retrieved from GitHub releases at `thedevcave/hubspot-sync-milli`
- The plugin checks for new releases every 12 hours
- Update notifications appear in WordPress Admin → Plugins
- Updates install the `hubspot-sync-milli.zip` asset from GitHub releases

## Troubleshooting

If updates aren't appearing:

1. **Clear update cache** by adding this to functions.php temporarily:
   ```php
   delete_transient('hubspot_sync_milli_latest_release');
   delete_site_transient('update_plugins');
   ```

2. **Enable debug logging** in wp-config.php:
   ```php
   define( 'WP_DEBUG_LOG', true );
   ```
   Check `/wp-content/debug.log` for updater messages.

3. **Check GitHub release**:
   - Ensure the release is published (not draft)
   - Verify `hubspot-sync-milli.zip` is attached as an asset
   - Confirm the repository is public