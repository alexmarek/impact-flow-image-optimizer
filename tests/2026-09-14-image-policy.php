<?php
/**
 * Standalone regression checks for the image policy.
 *
 * @package ImpactFlow_Image_Optimizer
 */

namespace ImpactFlow\Image_Optimizer;

define( 'ABSPATH', __DIR__ );
define( 'IFIO_VERSION', 'test' );

$ifio_test_option  = array();
$ifio_test_support = array(
	'image/webp' => true,
	'image/avif' => false,
);

/** WordPress sanitization stub. */
function sanitize_key( string $value ): string {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
}

/** WordPress integer stub. */
function absint( $value ): int {
	return abs( (int) $value );
}

/** WordPress option stub. */
function get_option( string $name, $default = false ) {
	unset( $name );
	global $ifio_test_option;
	return $ifio_test_option ?: $default;
}

/** WordPress editor capability stub. */
function wp_image_editor_supports( array $args ): bool {
	global $ifio_test_support;
	return ! empty( $ifio_test_support[ $args['mime_type'] ] );
}

require_once dirname( __DIR__ ) . '/includes/class-image-policy.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';

/**
 * Fail with a useful message.
 */
function ifio_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$defaults = Image_Policy::defaults();
ifio_assert( 2560 === $defaults['max_dimension'], 'Default maximum dimension changed.' );
ifio_assert( 'webp' === $defaults['output_format'], 'WebP should remain the default derivative format.' );
ifio_assert( true === $defaults['convert_png'], 'PNG conversion should be enabled by default.' );

$sanitized = Image_Policy::sanitize(
	array(
		'max_dimension' => 99999,
		'output_format' => 'invalid',
		'jpeg_quality'  => 2,
		'webp_quality'  => 101,
		'avif_quality'  => 1,
	)
);
ifio_assert( 5120 === $sanitized['max_dimension'], 'Maximum dimension should be bounded.' );
ifio_assert( 'webp' === $sanitized['output_format'], 'Invalid output format should use the default.' );
ifio_assert( 40 === $sanitized['jpeg_quality'], 'JPEG quality should be bounded.' );
ifio_assert( 95 === $sanitized['webp_quality'], 'WebP quality should be bounded.' );
ifio_assert( 30 === $sanitized['avif_quality'], 'AVIF quality should be bounded.' );

$ifio_test_option = $defaults;
$policy           = new Image_Policy();
ifio_assert( 2560 === $policy->filter_threshold( false ), 'A disabled upstream threshold should not cause a type error.' );
$formats          = $policy->filter_output_format( array( 'image/png' => 'image/png' ), 'photo.jpg', 'image/jpeg' );
ifio_assert( 'image/webp' === $formats['image/jpeg'], 'Supported WebP should be selected for JPEG derivatives.' );
ifio_assert( 'image/png' === $formats['image/png'], 'Existing mappings should be preserved.' );

$formats = $policy->filter_output_format( array(), null, 'image/jpeg' );
ifio_assert( 'image/webp' === $formats['image/jpeg'], 'A null target filename from WordPress should remain supported.' );

$formats = $policy->filter_output_format( array(), 'graphic.png', 'image/png' );
ifio_assert( 'image/webp' === $formats['image/png'], 'Enabled PNG derivatives should use the selected supported format.' );

$ifio_test_option['convert_png'] = false;
$formats                         = $policy->filter_output_format( array(), 'graphic.png', 'image/png' );
ifio_assert( array() === $formats, 'Disabled PNG conversion should leave PNG mappings unchanged.' );
$ifio_test_option['convert_png'] = true;

$ifio_test_option['output_format'] = 'avif';
$formats                           = $policy->filter_output_format( array( 'image/jpeg' => 'image/webp' ), 'photo.jpg', 'image/jpeg' );
ifio_assert( ! isset( $formats['image/jpeg'] ), 'Unsupported AVIF should fall back without forcing a mapping.' );

$ifio_test_option['output_format'] = 'original';
$formats                           = $policy->filter_output_format( array( 'image/jpeg' => 'image/webp' ), 'photo.jpg', 'image/jpeg' );
ifio_assert( array() === $formats, 'Original format should remove a competing JPEG conversion mapping.' );

$ifio_test_option = $defaults;
ifio_assert( 82 === $policy->filter_quality( 90, 'image/webp', array( 1200, 800 ) ), 'WebP quality setting should apply.' );
ifio_assert( 90 === $policy->filter_quality( 90, 'image/png', array( 1200, 800 ) ), 'Unmanaged formats should retain their quality.' );
ifio_assert( true === $policy->filter_progressive( false, 'image/jpeg' ), 'Progressive JPEG should be enabled by default.' );
ifio_assert( false === $policy->filter_progressive( false, 'image/png' ), 'Progressive policy should not alter PNG output.' );

echo "Image policy regression checks passed.\n";
