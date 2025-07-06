# Scripts Directory

This directory contains utility scripts for the FinAegis project.

## Favicon Generation

To regenerate favicons with updated branding:

```bash
php scripts/generate-favicons-simple.php
```

This will create:
- Multiple PNG sizes for different devices
- SVG version
- Apple Touch Icons
- Android Chrome icons
- Microsoft Tile images
- manifest.json for PWA support
- browserconfig.xml for Microsoft browsers

The favicon design features the "FA" letters representing FinAegis with a gradient background using the brand colors (indigo to purple).

### Updating the Design

To modify the favicon design, edit the SVG content in `generate-favicons-simple.php` and re-run the script.