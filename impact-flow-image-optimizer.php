<?php
/**
 * Plugin Name:       Impact Flow Image Optimizer
 * Plugin URI:        https://github.com/alexmarek/impact-flow-image-optimizer
 * Description:       A conservative upload-time image policy for fast, accessible Impact Flow client sites.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Alex Marek (Infinity Seeker)
 * Author URI:        https://github.com/alexmarek
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       impact-flow-image-optimizer
 *
 * @package ImpactFlow_Image_Optimizer
 */

namespace ImpactFlow\Image_Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IFIO_VERSION', '0.1.0' );
define( 'IFIO_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-image-policy.php';
require_once __DIR__ . '/includes/class-plugin.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->register();
	}
);
