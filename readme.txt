=== Impact Flow Image Optimizer ===
Contributors: alexmarek
Tags: images, webp, avif, performance, accessibility
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A conservative upload-time image policy for fast, accessible Impact Flow client sites.

== Description ==

Impact Flow Image Optimizer configures WordPress's native image pipeline for
new uploads. It caps oversized images, creates modern JPEG derivatives when
supported, applies format-aware quality values, and exposes image health in the
Media Library and Site Health.

The source upload and WordPress attachment record remain recoverable. Existing
media is never rewritten automatically. PNG derivative conversion can be
enabled or disabled, and attachment details distinguish decorative images from
missing alternative-text decisions.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Review Settings > Media and Tools > Site Health.

== Changelog ==

= 0.1.0 =
* Add upload dimension, output format, quality, progressive encoding and metadata policies.
* Add Media Library image-health feedback.
* Add Site Health capability reporting.
