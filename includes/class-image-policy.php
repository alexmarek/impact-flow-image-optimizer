<?php
/**
 * Upload-time image policy.
 *
 * @package ImpactFlow_Image_Optimizer
 */

namespace ImpactFlow\Image_Optimizer;

/**
 * Normalizes settings and applies them to WordPress image filters.
 */
final class Image_Policy {
	/**
	 * Default settings.
	 *
	 * @return array<string, int|string|bool>
	 */
	public static function defaults(): array {
		return array(
			'max_dimension'  => 2560,
			'output_format' => 'webp',
			'convert_png'   => true,
			'jpeg_quality'  => 82,
			'webp_quality'  => 82,
			'avif_quality'  => 70,
			'progressive'   => true,
			'strip_metadata' => true,
		);
	}

	/**
	 * Sanitize a settings payload.
	 *
	 * @param mixed $value Raw option value.
	 * @return array<string, int|string|bool>
	 */
	public static function sanitize( $value ): array {
		$value    = is_array( $value ) ? $value : array();
		$defaults = self::defaults();
		$format   = isset( $value['output_format'] ) ? sanitize_key( (string) $value['output_format'] ) : $defaults['output_format'];

		if ( ! in_array( $format, array( 'original', 'webp', 'avif' ), true ) ) {
			$format = $defaults['output_format'];
		}

		return array(
			'max_dimension'  => self::bounded_int( $value['max_dimension'] ?? $defaults['max_dimension'], 1600, 5120 ),
			'output_format' => $format,
			'convert_png'   => ! empty( $value['convert_png'] ),
			'jpeg_quality'  => self::bounded_int( $value['jpeg_quality'] ?? $defaults['jpeg_quality'], 40, 95 ),
			'webp_quality'  => self::bounded_int( $value['webp_quality'] ?? $defaults['webp_quality'], 40, 95 ),
			'avif_quality'  => self::bounded_int( $value['avif_quality'] ?? $defaults['avif_quality'], 30, 90 ),
			'progressive'   => ! empty( $value['progressive'] ),
			'strip_metadata' => ! empty( $value['strip_metadata'] ),
		);
	}

	/**
	 * Merge saved data over defaults.
	 *
	 * @param mixed $value Saved option value.
	 * @return array<string, int|string|bool>
	 */
	public static function normalize( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::defaults();
		}

		return self::sanitize( array_merge( self::defaults(), $value ) );
	}

	/**
	 * Set WordPress's large-image threshold.
	 *
	 * @param int|false $threshold Current threshold. Another plugin may disable it.
	 * @return int
	 */
	public function filter_threshold( $threshold ): int {
		unset( $threshold );
		return (int) Plugin::settings()['max_dimension'];
	}

	/**
	 * Map configured raster derivatives to the selected modern format.
	 *
	 * @param array<string, string> $formats Existing format map.
	 * @param string|null           $filename Source filename. Core may pass null while saving a sub-size.
	 * @param string                $mime_type Source MIME type.
	 * @return array<string, string>
	 */
	public function filter_output_format( array $formats, ?string $filename, string $mime_type ): array {
		unset( $filename );

		$settings        = Plugin::settings();
		$format          = (string) $settings['output_format'];
		$managed_sources = array( 'image/jpeg' );
		if ( $settings['convert_png'] ) {
			$managed_sources[] = 'image/png';
		}

		if ( ! in_array( $mime_type, $managed_sources, true ) ) {
			return $formats;
		}

		// This plugin owns the enabled source policy. Remove a competing mapping
		// before applying the selected format or its safe original fallback.
		unset( $formats[ $mime_type ] );
		if ( 'original' === $format ) {
			return $formats;
		}

		$output_mime = 'image/' . $format;
		if ( wp_image_editor_supports( array( 'mime_type' => $output_mime ) ) ) {
			$formats[ $mime_type ] = $output_mime;
		}

		return $formats;
	}

	/**
	 * Apply format-specific output quality.
	 *
	 * @param int                  $quality   Existing quality.
	 * @param string               $mime_type Output MIME type.
	 * @param array<string, mixed> $size      Output dimensions.
	 * @return int
	 */
	public function filter_quality( int $quality, string $mime_type, array $size ): int {
		unset( $size );
		$settings = Plugin::settings();
		$map      = array(
			'image/jpeg' => 'jpeg_quality',
			'image/webp' => 'webp_quality',
			'image/avif' => 'avif_quality',
		);

		if ( ! isset( $map[ $mime_type ] ) ) {
			return $quality;
		}

		return (int) $settings[ $map[ $mime_type ] ];
	}

	/**
	 * Produce progressive JPEG files where the editor supports them.
	 *
	 * @param bool   $progressive Existing value.
	 * @param string $mime_type   Output MIME type.
	 * @return bool
	 */
	public function filter_progressive( bool $progressive, string $mime_type ): bool {
		if ( 'image/jpeg' !== $mime_type ) {
			return $progressive;
		}

		return (bool) Plugin::settings()['progressive'];
	}

	/**
	 * Strip camera metadata from generated derivatives while retaining color data.
	 *
	 * @param bool $strip Existing value.
	 * @return bool
	 */
	public function filter_strip_metadata( bool $strip ): bool {
		unset( $strip );
		return (bool) Plugin::settings()['strip_metadata'];
	}

	/**
	 * Constrain an integer.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	private static function bounded_int( $value, int $min, int $max ): int {
		$value = absint( $value );
		return max( $min, min( $max, $value ) );
	}
}
