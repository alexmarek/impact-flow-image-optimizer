# Image policy decision

## Purpose

Impact Flow sites need predictable image performance without depending on a
specific host, CDN or paid optimisation service. Editors should continue using
the normal WordPress Media Library and image blocks.

## First-release contract

The plugin configures WordPress's native image pipeline for new uploads. It
does not replace attachment records, rewrite block markup or intercept image
delivery on the front end.

JPEG and PNG derivatives use WebP by default when the active image editor
supports it. The source upload remains recoverable and PNG transparency is
retained. PNG conversion is a site-level switch because lossy output can be a
regression for text-heavy screenshots and line art. AVIF is available as an
explicit site-level choice and falls back safely if unsupported.

The maximum dimension and quality values are policy settings rather than
theme settings. They therefore remain active if a client changes or updates
the theme.

## Accessibility

Alternative text describes an image in its page context. Empty alternative
text can be correct for decoration, so the plugin never fabricates text or
blocks an upload. Attachment details provide an explicit "decorative" decision,
and the Media Library distinguishes meaningful alternative text, confirmed
decoration and an unresolved review state. This gives the site owner an
editorial queue without turning a valid empty alt attribute into a technical
failure.

## Privacy and metadata

Generated files strip EXIF, IPTC and XMP data by default to remove avoidable
bytes and possible camera or location data. WordPress attachment fields,
attachment IDs and its generated metadata remain intact. Required colour
information is preserved by the WordPress image editor.

## Deferred existing-library tool

Existing attachments require an explicit bulk operation. It should provide a
dry-run count and estimated work, process resumable batches, acquire a per-item
lock, report failures without losing the original, and never run merely because
the plugin was activated. That tool is deliberately outside version 0.1.0.
