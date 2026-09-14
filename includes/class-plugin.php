<?php
/**
 * Plugin orchestration and admin integration.
 *
 * @package ImpactFlow_Image_Optimizer
 */

namespace ImpactFlow\Image_Optimizer;

/**
 * Registers the image policy and its operational feedback.
 */
final class Plugin {
	public const OPTION = 'impactflow_image_optimizer_settings';

	/** @var self|null */
	private static ?self $instance = null;

	/** @var Image_Policy */
	private Image_Policy $policy;

	/**
	 * Return the plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add defaults without autoloading the option on front-end requests.
	 */
	public static function activate(): void {
		add_option( self::OPTION, Image_Policy::defaults(), '', false );
	}

	/**
	 * Return normalized settings.
	 *
	 * @return array<string, int|string|bool>
	 */
	public static function settings(): array {
		return Image_Policy::normalize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Set up dependencies.
	 */
	private function __construct() {
		$this->policy = new Image_Policy();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'big_image_size_threshold', array( $this->policy, 'filter_threshold' ) );
		add_filter( 'image_editor_output_format', array( $this->policy, 'filter_output_format' ), 10, 3 );
		add_filter( 'wp_editor_set_quality', array( $this->policy, 'filter_quality' ), 10, 3 );
		add_filter( 'image_save_progressive', array( $this->policy, 'filter_progressive' ), 10, 2 );
		add_filter( 'image_strip_meta', array( $this->policy, 'filter_strip_metadata' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_fields' ), 10, 2 );
		add_filter( 'site_status_tests', array( $this, 'add_site_health_tests' ) );
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
	}

	/**
	 * Add the plugin settings to Settings > Media.
	 */
	public function register_settings(): void {
		register_setting(
			'media',
			self::OPTION,
			array(
				'type'              => 'array',
				'default'           => Image_Policy::defaults(),
				'sanitize_callback' => array( Image_Policy::class, 'sanitize' ),
			)
		);

		add_settings_section(
			'ifio_settings',
			esc_html__( 'Impact Flow image optimisation', 'impact-flow-image-optimizer' ),
			array( $this, 'render_settings_intro' ),
			'media'
		);

		$fields = array(
			'max_dimension'  => esc_html__( 'Maximum dimension', 'impact-flow-image-optimizer' ),
			'output_format' => esc_html__( 'JPEG derivatives', 'impact-flow-image-optimizer' ),
			'convert_png'   => esc_html__( 'PNG derivatives', 'impact-flow-image-optimizer' ),
			'jpeg_quality'  => esc_html__( 'JPEG quality', 'impact-flow-image-optimizer' ),
			'webp_quality'  => esc_html__( 'WebP quality', 'impact-flow-image-optimizer' ),
			'avif_quality'  => esc_html__( 'AVIF quality', 'impact-flow-image-optimizer' ),
			'progressive'   => esc_html__( 'Progressive JPEG', 'impact-flow-image-optimizer' ),
			'strip_metadata' => esc_html__( 'Camera metadata', 'impact-flow-image-optimizer' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'ifio_' . $key,
				$label,
				array( $this, 'render_setting_field' ),
				'media',
				'ifio_settings',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * Explain the settings scope.
	 */
	public function render_settings_intro(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'These defaults apply to new uploads. The original source and WordPress attachment record remain recoverable. Existing media is not rewritten automatically.', 'impact-flow-image-optimizer' )
		);
		printf(
			'<p><strong>%1$s</strong> %2$s</p>',
			esc_html__( 'Effective upload limit:', 'impact-flow-image-optimizer' ),
			esc_html( size_format( wp_max_upload_size() ) )
		);
		if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
			printf(
				'<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'This limit is controlled by Multisite network settings.', 'impact-flow-image-optimizer' ),
				esc_url( network_admin_url( 'settings.php#upload_filetypes' ) ),
				esc_html__( 'Open Network Settings', 'impact-flow-image-optimizer' )
			);
		}
	}

	/**
	 * Render one settings field.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public function render_setting_field( array $args ): void {
		$key      = $args['key'];
		$settings = self::settings();
		$name     = self::OPTION . '[' . $key . ']';

		switch ( $key ) {
			case 'max_dimension':
			case 'jpeg_quality':
			case 'webp_quality':
			case 'avif_quality':
				$limits = 'max_dimension' === $key ? array( 1600, 5120 ) : array( 'avif_quality' === $key ? 30 : 40, 'avif_quality' === $key ? 90 : 95 );
				printf(
					'<input type="number" class="small-text" name="%1$s" value="%2$d" min="%3$d" max="%4$d" step="1" />',
					esc_attr( $name ),
					(int) $settings[ $key ],
					(int) $limits[0],
					(int) $limits[1]
				);
				if ( 'max_dimension' === $key ) {
					echo ' <span class="description">' . esc_html__( 'pixels on the longest edge', 'impact-flow-image-optimizer' ) . '</span>';
				}
				break;

			case 'output_format':
				$options = array(
					'original' => esc_html__( 'Keep original format', 'impact-flow-image-optimizer' ),
					'webp'     => esc_html__( 'WebP (recommended)', 'impact-flow-image-optimizer' ),
					'avif'     => esc_html__( 'AVIF', 'impact-flow-image-optimizer' ),
				);
				printf( '<select name="%s">', esc_attr( $name ) );
				foreach ( $options as $value => $label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $settings[ $key ], $value, false ), esc_html( $label ) );
				}
				echo '</select><p class="description">' . esc_html__( 'Applied to JPEG derivatives only when the server supports the selected format. PNG, SVG and animated GIF files are left unchanged.', 'impact-flow-image-optimizer' ) . '</p>';
				break;

			case 'progressive':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( (bool) $settings[ $key ], true, false ),
					esc_html__( 'Use progressive encoding for generated JPEG files.', 'impact-flow-image-optimizer' )
				);
				break;

			case 'convert_png':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label><p class="description">%4$s</p>',
					esc_attr( $name ),
					checked( (bool) $settings[ $key ], true, false ),
					esc_html__( 'Convert PNG derivatives to the selected modern format.', 'impact-flow-image-optimizer' ),
					esc_html__( 'Transparency is retained. Disable this for sites whose PNG library relies heavily on lossless text, diagrams or screenshots.', 'impact-flow-image-optimizer' )
				);
				break;

			case 'strip_metadata':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label><p class="description">%4$s</p>',
					esc_attr( $name ),
					checked( (bool) $settings[ $key ], true, false ),
					esc_html__( 'Strip EXIF, IPTC and XMP data from generated files.', 'impact-flow-image-optimizer' ),
					esc_html__( 'Reduces weight and removes camera or location data. WordPress attachment fields and required colour information remain intact.', 'impact-flow-image-optimizer' )
				);
				break;
		}
	}

	/**
	 * Add an image-health column to the Media Library list view.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_media_column( array $columns ): array {
		$columns['ifio_health'] = esc_html__( 'Image health', 'impact-flow-image-optimizer' );
		return $columns;
	}

	/**
	 * Show format and alternative-text state without changing editorial data.
	 *
	 * @param string $column_name Column name.
	 * @param int    $attachment_id Attachment ID.
	 */
	public function render_media_column( string $column_name, int $attachment_id ): void {
		if ( 'ifio_health' !== $column_name || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$mimes    = array();
		foreach ( is_array( $metadata ) ? ( $metadata['sizes'] ?? array() ) : array() as $size ) {
			if ( ! empty( $size['mime-type'] ) ) {
				$mimes[] = (string) $size['mime-type'];
			}
		}

		$modern = in_array( 'image/avif', $mimes, true ) ? 'AVIF' : ( in_array( 'image/webp', $mimes, true ) ? 'WebP' : '' );
		if ( $modern ) {
			printf( '<strong>%s</strong><br />', esc_html( sprintf( /* translators: %s: image format. */ __( '%s derivatives', 'impact-flow-image-optimizer' ), $modern ) ) );
		} else {
			echo '<span>' . esc_html__( 'Original-format derivatives', 'impact-flow-image-optimizer' ) . '</span><br />';
		}

		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			if ( get_post_meta( $attachment_id, '_ifio_decorative', true ) ) {
				echo '<span>' . esc_html__( 'Decorative: confirmed', 'impact-flow-image-optimizer' ) . '</span>';
			} else {
				echo '<span style="color:#b32d2e">' . esc_html__( 'Alt text: review required', 'impact-flow-image-optimizer' ) . '</span>';
			}
		} else {
			echo '<span>' . esc_html__( 'Alt text: present', 'impact-flow-image-optimizer' ) . '</span>';
		}
	}

	/**
	 * Add an explicit decorative-image decision to attachment details.
	 *
	 * @param array<string, mixed> $fields Existing attachment fields.
	 * @param \WP_Post              $post   Attachment post.
	 * @return array<string, mixed>
	 */
	public function add_attachment_fields( array $fields, \WP_Post $post ): array {
		if ( ! wp_attachment_is_image( $post ) ) {
			return $fields;
		}

		$decorative = (bool) get_post_meta( $post->ID, '_ifio_decorative', true );
		$fields['ifio_decorative'] = array(
			'label' => esc_html__( 'Accessibility', 'impact-flow-image-optimizer' ),
			'input' => 'html',
			'html'  => sprintf(
				'<label><input type="checkbox" name="attachments[%1$d][ifio_decorative]" value="1"%2$s /> %3$s</label>',
				$post->ID,
				checked( $decorative, true, false ),
				esc_html__( 'This image is decorative; empty alternative text is intentional.', 'impact-flow-image-optimizer' )
			),
			'helps' => esc_html__( 'For an informative image, leave this unchecked and write alternative text that explains its purpose in the page.', 'impact-flow-image-optimizer' ),
		);

		return $fields;
	}

	/**
	 * Save the decorative-image decision.
	 *
	 * @param array<string, mixed> $post       Attachment data.
	 * @param array<string, mixed> $attachment Submitted fields.
	 * @return array<string, mixed>
	 */
	public function save_attachment_fields( array $post, array $attachment ): array {
		$attachment_id = isset( $post['ID'] ) ? absint( $post['ID'] ) : 0;
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return $post;
		}

		$alt = isset( $attachment['image_alt'] ) ? trim( sanitize_text_field( $attachment['image_alt'] ) ) : (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' !== $alt || empty( $attachment['ifio_decorative'] ) ) {
			delete_post_meta( $attachment_id, '_ifio_decorative' );
		} else {
			update_post_meta( $attachment_id, '_ifio_decorative', '1' );
		}

		return $post;
	}

	/**
	 * Add direct capability checks to Site Health.
	 *
	 * @param array<string, mixed> $tests Existing tests.
	 * @return array<string, mixed>
	 */
	public function add_site_health_tests( array $tests ): array {
		$tests['direct']['ifio_output_format'] = array(
			'label' => esc_html__( 'Impact Flow image output support', 'impact-flow-image-optimizer' ),
			'test'  => array( $this, 'test_output_format' ),
		);
		$tests['direct']['ifio_upload_limit'] = array(
			'label' => esc_html__( 'Impact Flow image upload limit', 'impact-flow-image-optimizer' ),
			'test'  => array( $this, 'test_upload_limit' ),
		);
		return $tests;
	}

	/**
	 * Report restrictive host or Multisite upload limits.
	 *
	 * @return array<string, mixed>
	 */
	public function test_upload_limit(): array {
		$limit      = wp_max_upload_size();
		$recommended = 10 * MB_IN_BYTES;
		$sufficient = $limit >= $recommended;
		$actions    = '';

		if ( ! $sufficient && is_multisite() && current_user_can( 'manage_network_options' ) ) {
			$actions = sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( network_admin_url( 'settings.php#upload_filetypes' ) ),
				esc_html__( 'Review Multisite network settings', 'impact-flow-image-optimizer' )
			);
		}

		return array(
			'label'       => $sufficient
				? esc_html__( 'The image upload limit is suitable', 'impact-flow-image-optimizer' )
				: esc_html__( 'The image upload limit may be too restrictive', 'impact-flow-image-optimizer' ),
			'status'      => $sufficient ? 'good' : 'recommended',
			'badge'       => array(
				'label' => esc_html__( 'Performance', 'impact-flow-image-optimizer' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html( sprintf( /* translators: %s: effective upload limit. */ __( 'The effective upload limit is %s. Impact Flow recommends at least 10 MB so editors can upload a high-quality source before derivatives are generated.', 'impact-flow-image-optimizer' ), size_format( $limit ) ) )
			),
			'actions'     => $actions,
			'test'        => 'ifio_upload_limit',
		);
	}

	/**
	 * Verify that the configured modern format is available.
	 *
	 * @return array<string, mixed>
	 */
	public function test_output_format(): array {
		$format    = (string) self::settings()['output_format'];
		$supported = 'original' === $format || wp_image_editor_supports( array( 'mime_type' => 'image/' . $format ) );
		if ( 'original' === $format ) {
			$label       = esc_html__( 'Modern-format conversion is disabled', 'impact-flow-image-optimizer' );
			$description = esc_html__( 'New uploads retain their source format. Select WebP or AVIF under Settings → Media to enable conversion.', 'impact-flow-image-optimizer' );
		} elseif ( $supported ) {
			$label       = esc_html__( 'The configured image format is supported', 'impact-flow-image-optimizer' );
			$description = esc_html( sprintf( /* translators: %s: selected format. */ __( 'New JPEG uploads can generate %s derivatives with the active WordPress image editor.', 'impact-flow-image-optimizer' ), strtoupper( $format ) ) );
		} else {
			$label       = esc_html__( 'The configured image format is unavailable', 'impact-flow-image-optimizer' );
			$description = esc_html( sprintf( /* translators: %s: selected format. */ __( 'The active WordPress image editor cannot generate %s. Uploads will retain their original format until server support or the setting changes.', 'impact-flow-image-optimizer' ), strtoupper( $format ) ) );
		}

		return array(
			'label'       => $label,
			'status'      => $supported ? 'good' : 'recommended',
			'badge'       => array(
				'label' => esc_html__( 'Performance', 'impact-flow-image-optimizer' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', $description ),
			'test'        => 'ifio_output_format',
		);
	}

	/**
	 * Expose effective settings in Site Health information.
	 *
	 * @param array<string, mixed> $info Existing debug information.
	 * @return array<string, mixed>
	 */
	public function add_debug_information( array $info ): array {
		$settings = self::settings();
		$info['impact-flow-image-optimizer'] = array(
			'label'  => esc_html__( 'Impact Flow Image Optimizer', 'impact-flow-image-optimizer' ),
			'fields' => array(
				'version'       => array( 'label' => esc_html__( 'Version', 'impact-flow-image-optimizer' ), 'value' => IFIO_VERSION ),
				'max_dimension' => array( 'label' => esc_html__( 'Maximum dimension', 'impact-flow-image-optimizer' ), 'value' => (string) $settings['max_dimension'] ),
				'output_format' => array( 'label' => esc_html__( 'JPEG derivatives', 'impact-flow-image-optimizer' ), 'value' => (string) $settings['output_format'] ),
				'convert_png'   => array( 'label' => esc_html__( 'Convert PNG derivatives', 'impact-flow-image-optimizer' ), 'value' => $settings['convert_png'] ? 'true' : 'false' ),
				'upload_limit'  => array( 'label' => esc_html__( 'Upload limit', 'impact-flow-image-optimizer' ), 'value' => size_format( wp_max_upload_size() ) ),
				'webp_support'  => array( 'label' => esc_html__( 'WebP support', 'impact-flow-image-optimizer' ), 'value' => wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'true' : 'false' ),
				'avif_support'  => array( 'label' => esc_html__( 'AVIF support', 'impact-flow-image-optimizer' ), 'value' => wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ? 'true' : 'false' ),
			),
		);
		return $info;
	}
}
