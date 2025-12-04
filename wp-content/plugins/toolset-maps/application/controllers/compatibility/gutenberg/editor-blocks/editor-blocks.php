<?php
namespace OTGS\Toolset\Maps\Controller\Compatibility\Gutenberg\EditorBlocks;

use Toolset_Condition_Plugin_Gutenberg_Active;
use Toolset_Gutenberg_Block_REST_Helper;
use OTGS\Toolset\Maps\Controller\Compatibility\Gutenberg\EditorBlocks\Blocks\MapsEditorBlockFactory;
use OTGS\Toolset\Maps\Controller\Compatibility\Gutenberg\EditorBlocks\Blocks\Map\MapBlock;

/**
 * Class MapsEditorBlocks
 *
 * Handles the creation and initialization of the all the new editor (Gutenberg) integration stuff.
 *
 * @package OTGS\Toolset\Maps\Controller\Compatibility
 * @since 1.7
 */
class MapsEditorBlocks {
	const TOOLSET_MAPS_BLOCKS_ASSETS_RELATIVE_PATH = '/public';

	/**
	 * Initializes the Views Gutenberg blocks.
	 */
	public function initialize() {
		if ( !class_exists( 'Toolset_Condition_Plugin_Gutenberg_Active' ) ) return;

		$gutenberg_active = new Toolset_Condition_Plugin_Gutenberg_Active();

		if ( ! $gutenberg_active->is_met() ) {
			return;
		}

		// Init blocks
		$maps_blocks = array(
			MapBlock::BLOCK_NAME,
		);

		$factory = new MapsEditorBlockFactory();
		new Toolset_Gutenberg_Block_REST_Helper();

		foreach ( $maps_blocks as $maps_block_name ) {
			$block = $factory->get_block( $maps_block_name );
			if ( null !== $block ) {
				$block->init_hooks();
			};
		}
	}
}
