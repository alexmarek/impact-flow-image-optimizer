# Impact Flow Image Optimizer

A small upload-time image policy for Impact Flow client sites. It uses
WordPress's own image editor and attachment metadata rather than a remote
service or a replacement media library.

**Status:** `0.1.0` — first review release for new uploads.

## What it does

- Caps new raster uploads at 2560px on the longest edge by default.
- Converts JPEG and, by default, PNG derivatives to WebP when the active
  WordPress image editor supports it. PNG conversion can be disabled for sites
  dominated by lossless screenshots or line art. SVG and animated GIF files
  are left in their source format.
- Applies conservative, configurable JPEG, WebP and AVIF quality values.
- Enables progressive JPEG output and strips camera metadata from generated
  files by default.
- Keeps the source upload and WordPress attachment record recoverable.
- Adds an **Image health** column to the Media Library list view. It reports
  the derivative format and distinguishes meaningful alternative text,
  confirmed decoration, and images still needing an editorial decision.
- Adds the effective configuration and output support to **Tools → Site
  Health**.

The plugin does not fabricate alternative text. Empty alt text is correct for
decorative images, so the Media Library reports **Alt text: review** instead of
treating every empty value as an error.

## Requirements

- WordPress 6.7+
- PHP 8.1+
- A WordPress image editor with WebP support for the recommended default

WordPress 7.1 applies the same image filters during its browser-side media
processing. Earlier supported releases apply them through the server image
editor.

## Install

1. Copy this directory to
   `wp-content/plugins/impact-flow-image-optimizer`.
2. Activate **Impact Flow Image Optimizer** under **Plugins**.
3. Review the defaults under **Settings → Media**.
4. Check **Tools → Site Health** for modern-format support.

## Default policy

| Setting | Default | Reason |
|---|---:|---|
| Maximum dimension | 2560px | Matches WordPress's established large-image threshold and supports large hero use without retaining camera-sized derivatives. |
| JPEG derivatives | WebP | Broad browser and WordPress support with a meaningful reduction for photographic content. |
| PNG conversion | On | Generates modern derivatives while retaining transparency; can be disabled for lossless-oriented libraries. |
| JPEG quality | 82 | Balanced photographic output. |
| WebP quality | 82 | Conservative enough for client imagery and large crops. |
| AVIF quality | 70 | Available as an explicit option when the host supports it. |
| Progressive JPEG | On | Improves perceived loading when JPEG output remains. |
| Strip camera metadata | On | Removes avoidable weight and private camera/location data from generated files. |

Automatic PNG conversion can be a regression for already-small lossless
graphics, text-heavy screenshots or line art. It is therefore exposed as a
site-level switch rather than hard-coded behavior.

## Existing media

Version 0.1.0 does not rewrite existing attachments. A bulk optimiser is
planned as a separate, explicit operation with a dry-run summary and resumable
batches. This avoids a plugin activation unexpectedly consuming server CPU or
changing a live site's media library.

## Development

```sh
composer install
composer test
```

`composer test` runs PHP syntax checks, the standalone policy regression test,
and the WordPress VIP coding standard. Runtime code has no Composer dependency.

## Compatibility contract

- Attachment IDs and WordPress attachment fields remain unchanged.
- Source uploads remain available through WordPress's normal original-image
  handling.
- Unsupported output formats fall back to the source format.
- Other plugins' format mappings are preserved except for the JPEG mapping
  selected here.
- Deactivation returns new uploads to WordPress core behavior.

## Licence

GPL-2.0-or-later. © Alex Marek (Infinity Seeker).
