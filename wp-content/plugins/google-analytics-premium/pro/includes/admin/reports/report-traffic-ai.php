<?php
/**
 * Traffic AI Report
 *
 * Ensures all the reports have a uniform class with helper functions.
 *
 * @package MonsterInsights
 * @subpackage Reports
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_Traffic_AI extends MonsterInsights_Report {

	public $class = 'MonsterInsights_Report_Traffic_AI';
	public $name  = 'traffic_ai';
	public $level = 'plus';

	protected $api_path = 'traffic-ai';

	/**
	 * Primary class constructor.
	 */
	public function __construct() {
		$this->title = __( 'Traffic AI', 'google-analytics-premium' );

		parent::__construct();
	}

	/**
	 * Add necessary information to data for Vue reports.
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function prepare_report_data( $data ) {
		if ( isset( $data['data']['ai_table'] ) && is_array( $data['data']['ai_table'] ) ) {
			foreach ( $data['data']['ai_table'] as &$ai_source ) {
				$ai_source['icon'] = $this->get_ai_source_icon( $ai_source['platform'] );
			}
		}

		return apply_filters( 'monsterinsights_report_traffic_sessions_chart_data', $data, $this->start_date, $this->end_date );

		
	}

	/**
	 * Get AI source icon file name of SVG file.
	 *
	 * @return string|array
	 */
	private function get_ai_source_icon( $source ) {
		if ( is_array( $source ) ) {
			$source = $source[0];
		}

		$filename = strtolower( $source );

		// Map common AI sources to icons
		$ai_source_mapping = array(
			'chatgpt'        => 'chatgpt',
			'openai'         => 'openai',
			'claude'         => 'claude',
			'bard'           => 'bard',
			'gemini'         => 'gemini',
			'bing chat'      => 'bing',
			'bing ai'        => 'bing',
			'copilot'        => 'copilot',
			'perplexity'     => 'perplexity',
			'you.com'        => 'you',
			'character.ai'   => 'character-ai',
			'poe'            => 'poe',
			'huggingface'    => 'huggingface',
			'replicate'      => 'replicate',
			'grok'           => 'grok',
			'mistral'        => 'mistral',
			'midjourney'     => 'midjourney',
			'runway'         => 'runway',
			'stability'      => 'stability',
			'meta ai'        => 'metaai',
			'dall-e'          => 'dalle',
			'anthropic'      => 'anthropic',
			'deepseek'       => 'deepseek',
		);

		// Check if we have a specific mapping for this AI source
		foreach ( $ai_source_mapping as $ai_name => $icon_name ) {
			if ( stripos( $source, $ai_name ) !== false ) {
				$filename = $icon_name;
				break;
			}
		}

		// Check if icon file exists
		if ( file_exists( MONSTERINSIGHTS_PLUGIN_DIR . 'pro/assets/img/ai/icon-' . $filename . '.svg' ) ) {
			return $filename;
		}

		// Return generic AI icon if specific one doesn't exist
		return 'ai-generic';
	}
}
