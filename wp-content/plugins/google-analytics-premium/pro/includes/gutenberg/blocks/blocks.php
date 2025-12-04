<?php
/**
 * Gutenberg Blocks registration class.
 * Just for the blocks exclusive to the premium version.
 *
 * @since 7.13.9
 *
 * @package MonsterInsights
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg Blocks registration class.
 *
 * @since 7.13.0
 */
class MonsterInsights_Blocks_Pro {

	/**
	 * Holds the class object.
	 *
	 * @since 7.13.0
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * Path to the file.
	 *
	 * @since 7.13.0
	 *
	 * @var string
	 */
	public $file = __FILE__;

	/**
	 * Holds the base class object.
	 *
	 * @since 7.13.0
	 *
	 * @var object
	 */
	public $base;

	/**
	 * Primary class constructor.
	 *
	 * @since 7.13.0
	 */
	public function __construct() {

		if ( function_exists( 'register_block_type' ) ) {
			// this needs to be before block registration.
			if ( version_compare( get_bloginfo( 'version' ), '5.7', '>=' ) ) {
				add_filter( 'block_type_metadata', array( $this, 'enable_site_insights_inserter' ) );
			} else {
				add_filter( 'register_block_type_args', array( $this, 'enable_site_insights_inserter_wp56' ), 10, 2 );
			}

			// Set our object.
			$this->set();
			$this->register_blocks();

			// Register Site Insights assets here because on older WP versions(> 6.0) `register_block_type` was called before assets registration.
			$this->register_site_insights_assets();
		}
	}

	/**
	 * Sets our object instance and base class instance.
	 *
	 * @since 7.13.0
	 */
	public function set() {
		self::$instance = $this;
	}

	/**
	 * Register MonsterInsights Gutenberg blocks on the backend.
	 *
	 * @since 7.13.0
	 */
	public function register_blocks() {
		register_block_type(
			'monsterinsights/popular-posts-products',
			array(
				'attributes'      => array(
					'slug'        => array(
						'type' => 'string',
					),
					'followrules' => array(
						'type' => 'boolean',
					),
				),
				'render_callback' => array( $this, 'popular_posts_products_output' ),
			)
		);
		register_block_type(
			'monsterinsights/popular-posts-inline',
			array(
				'attributes'      => array(
					'slug'        => array(
						'type' => 'string',
					),
					'followrules' => array(
						'type' => 'boolean',
					),
				),
				'render_callback' => array( $this, 'popular_posts_inline_output' ),
			)
		);
		register_block_type(
			'monsterinsights/popular-posts-widget',
			array(
				'attributes'      => array(
					'slug'        => array(
						'type' => 'string',
					),
					'followrules' => array(
						'type' => 'boolean',
					),
				),
				'render_callback' => array( $this, 'popular_posts_widget_output' ),
			)
		);

		$site_insights_metadata = apply_filters(
			'monsterinsights_site_insights_metadata_path',
			__DIR__ . '/metadata/site-insights/'
		);

		register_block_type_from_metadata(
			$site_insights_metadata,
			array(
				'render_callback' => array( $this, 'site_insights_block_output' ),
			)
		);
	}

	/**
	 * Get form HTML to display in a MonsterInsights Gutenberg block.
	 *
	 * @param array $atts Attributes passed by MonsterInsights Gutenberg block.
	 *
	 * @return string
	 * @since 7.13.0
	 *
	 */
	public function popular_posts_products_output( $atts ) {
		$atts   = $this->add_default_values( $atts );
		$output = MonsterInsights_Popular_Posts_Products()->shortcode_output( $atts );

		return $output;
	}

	/**
	 * Get form HTML to display in a MonsterInsights Gutenberg block.
	 *
	 * @param array $atts Attributes passed by MonsterInsights Gutenberg block.
	 *
	 * @return string
	 * @since 7.13.0
	 *
	 */
	public function popular_posts_inline_output( $atts ) {
		$output = MonsterInsights_Popular_Posts_Inline()->shortcode_output( $atts );

		return $output;
	}

	/**
	 * Get form HTML to display in a MonsterInsights Gutenberg block.
	 *
	 * @param array $atts Attributes passed by MonsterInsights Gutenberg block.
	 *
	 * @return string
	 * @since 7.13.0
	 *
	 */
	public function popular_posts_widget_output( $atts ) {

		$atts   = $this->add_default_values( $atts );
		$output = MonsterInsights_Popular_Posts_Widget()->shortcode_output( $atts );

		return $output;
	}

	/**
	 * Outputs the HTML content for the stats block.
	 *
	 * @param $attributes
	 * @param $content
	 * @param $block
	 * @return string
	 */
	public function site_insights_block_output( $attributes, $content, $block ) {
		$output = new MonsterInsights_Site_Insights_Block();
		return $output->block_output( $attributes, $block );
	}

	public function register_site_insights_assets() {
		$output = new MonsterInsights_Site_Insights_Block();
		add_action('wp_enqueue_scripts', array( $output, 'register_frontend_scripts' ));
	}

	/**
	 * A filter added to `block_type_metadata` where we decide if the current user can see the block in the inserter.
	 *
	 * @param $metadata
	 * @return array|mixed
	 */
	public function enable_site_insights_inserter( $metadata ) {
		$metadata_file = __DIR__ . '/metadata/site-insights/block.json';
		// @TODO bring this back when we will support WordPress 5.9+
//		$metadata_from_file = wp_json_file_decode( $metadata_file, array( 'associative' => true ) );
		$metadata_from_file = json_decode( file_get_contents( $metadata_file ), true );

		if ( $metadata['name'] === $metadata_from_file['name'] ) {
			if ( current_user_can( 'monsterinsights_save_settings' ) ) {
				$metadata['supports']['inserter'] = true;
			}
		}

		return $metadata;
	}

	/**
	 * Filter block attributes in an era before `block_type_metadata`.
	 *
	 * @param $args
	 * @param $name
	 * @return array
	 */
	public function enable_site_insights_inserter_wp56( $args, $name ) {
		$metadata_file = __DIR__ . '/metadata/site-insights/block.json';
		// @TODO bring this back when we will support WordPress 5.9+
//		$metadata_from_file = wp_json_file_decode( $metadata_file, array( 'associative' => true ) );
		$metadata_from_file = json_decode( file_get_contents( $metadata_file ), true );

		if ( $name === $metadata_from_file['name'] ) {
			if ( current_user_can( 'monsterinsights_save_settings' ) ) {
				$args['supports']['inserter'] = true;
			}
		}

		return $args;
	}

	/**
	 * This ensures that what is displayed as default in the Gutenberg block is reflected in the output.
	 *
	 * @param array $atts The attributes from Gutenberg.
	 *
	 * @return array
	 */
	private function add_default_values( $atts ) {

		$default_values = array(
			'columns'      => 1,
			'widget_title' => false,
			'post_count'   => 5,
		);

		return wp_parse_args( $atts, $default_values );

	}
}

new MonsterInsights_Blocks_Pro();
