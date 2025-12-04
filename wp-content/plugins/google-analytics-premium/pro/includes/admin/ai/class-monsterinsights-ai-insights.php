<?php

namespace ai;

use MonsterInsights_API_Request;
use MonsterInsights_Rest_Routes;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * AI Insights class
 *
 * @author David Paternina
 */
class MonsterInsights_AI_Insights
{
	/**
	 * The transient key for the AI insights
	 */
	const INSIGHTS_TRANSIENT_KEY = 'monsterinsights_ai_insights';

	/**
	 * The option key for the AI insights rating
	 */
	const INSIGHTS_RATING_OPTION_KEY = 'monsterinsights_ai_insights_rating';

	/**
	 * Name of the option used to make sure we don't send the same feedback data multiple times
	 */
	const INSIGHTS_FEEDBACK_CHECKIN_LIST_KEY = 'monsterinsights_ai_insights_feedback_checkin_list';

	/**
	 * The transient key for the list of addons for AI
	 */
	const ADDONS_FOR_AI_TRANSIENT_KEY = 'monsterinsights_addons_for_ai';

	/**
	 * The transient key for the list of addons for AI
	 */
	const AI_INSIGHTS_READ_OPTION_KEY = 'monsterinsights_ai_insights_read';

	public function __construct()
	{
		//  We need to include the addons and routes file to get the list of addons
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/admin/pages/addons.php';
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/admin/routes.php';

		$this->init_hooks();
	}

	/**
	 * Init hooks
	 * @return void
	 */
	private function init_hooks()
	{
		//  Rest API
		add_action( 'rest_api_init', [$this, 'register_rest_endpoints'] );

		//  Cron
		add_action( 'monsterinsights_ai_insights_checkin', [$this, 'ratings_and_feedback_checkin'] );

		if ( ! wp_next_scheduled( 'monsterinsights_ai_insights_checkin' ) ) {
			wp_schedule_event( time(), 'daily', 'monsterinsights_ai_insights_checkin' );
		}

		//	Clear addons cache when addon is activated or deactivated
		add_action( 'monsterinsights_after_ajax_activate_addon', [$this, 'clear_cached_addons_for_ai'] );
		add_action( 'monsterinsights_after_ajax_deactivate_addon', [$this, 'clear_cached_addons_for_ai'] );
	}

	/**
	 * Register the AJAX endpoints
	 *
	 * @return void
	 */
	public function register_rest_endpoints()
	{
		register_rest_route( 'monsterinsights/v1', '/ai-insights', array(
			'methods'               => WP_REST_Server::READABLE,
			'callback'              => [$this, 'get_ai_insights'],
			'permission_callback'   => [$this, 'ai_insights_permissions_callback'],
		));

		register_rest_route( 'monsterinsights/v1', '/ai-insights/ratings', array(
			'methods'               => WP_REST_Server::READABLE,
			'callback'              => [$this, 'get_current_insights_ratings'],
			'permission_callback'   => [$this, 'ai_insights_permissions_callback'],
		));

		register_rest_route( 'monsterinsights/v1', '/ai-insights/rate', array(
			'methods'               => WP_REST_Server::CREATABLE,
			'callback'              => [$this, 'rate_insight'],
			'permission_callback'   => [$this, 'ai_insights_permissions_callback']
		));

		register_rest_route( 'monsterinsights/v1', '/ai-insights/feedback', array(
			'methods'               => WP_REST_Server::CREATABLE,
			'callback'              => [$this, 'save_insight_feedback'],
			'permission_callback'   => [$this, 'ai_insights_permissions_callback']
		));
	}

	/**
	 * Send ratings and feedback data to the API
	 * @return void
	 */
	public function ratings_and_feedback_checkin()
	{
		//  Get all ratings
		$ratings = $this->get_insights_ratings();

		//  If there are no ratings, bail
		if ( empty($ratings) ) {
			return;
		}

		$data = [];

		$checked_in_sets = $this->get_checked_in_set_keys();

		$newly_checked_sets = [];

		foreach ($ratings as $set_key => $set) {
			if ( in_array($set_key, $checked_in_sets) ) {
				continue;
			}

			$data[] = [
				'insights_data' => $set,
			];

			$newly_checked_sets[] = $set_key;
		}

		//  If there is no data, bail
		if ( empty($data) ) {
			return;
		}

		$api = new MonsterInsights_API_Request( 'analytics/ai/feedback', [], 'POST' );

		$result = $api->request([
			'ai_feedback' => $data
		]);

		if ( !is_wp_error( $result ) ) {
			//  Update checked sets
			$checked_in_sets = array_merge($checked_in_sets, $newly_checked_sets);
			$this->update_checked_in_set_keys($checked_in_sets);
		}
	}

	/**
	 * Check if the user has the required permissions
	 *
	 * @return bool
	 */
	public function ai_insights_permissions_callback()
	{
		// Check if the user has the required permissions
		return current_user_can( 'monsterinsights_save_settings' );
	}

	/**
	 * Check if the user has a valid license
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response|null
	 */
	public static function check_license()
	{
		if ( ! MonsterInsights()->license->is_site_licensed() && ! MonsterInsights()->license->is_network_licensed() ) {
			$settings_page    = admin_url( 'admin.php?page=monsterinsights_settings' );
			// Translators: Support link tag starts with url and Support link tag ends.
			$message = sprintf(
				esc_html__( 'Oops! You cannot view AI Insights and Conversations because you are not licensed. %1$sAdd your license%2$s. If the issue continues, please %3$scontact our support%4$s team.', 'google-analytics-premium' ),
				'<a target="_blank" href="' . $settings_page . '">',
				'</a>',
				'<a target="_blank" href="' . monsterinsights_get_url( 'notice', 'cannot-view-reports', 'https://www.monsterinsights.com/my-account/support/' ) . '">',
				'</a>'
			);
			return rest_ensure_response([
				'success' => false,
				'error' => $message
			]);
		}

		return null;
	}

	/**
	 * Get AI Insights
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_ai_insights( $request )
	{
		$license_check = $this->check_license();

		if ( !empty($license_check) ) {
			return $license_check;
		}

		//  Check request for manual refresh - Skip cache
		$force_refresh = ! empty( $request['force_refresh'] ) && $request['force_refresh'] === 'true';

		if ( !$force_refresh ) {
			//  Check cache
			$data = $this->get_cached_insights();

			if ( ! empty( $data ) ) {
				$insight_set_key = $this->get_insight_set_key( $data['insights'] );
				$ratings         = $this->get_insight_ratings_set( $insight_set_key );

				$ratings = array_map( function( $rating ) {
					return [
						'insight_id'    => $rating['insight']['id'],
						'score'         => $rating['score'],
						'skipped'       => isset( $rating['skipped']) ? $rating['skipped'] : false,
						'feedback'      => ! empty( $rating['feedback'] ) ? $rating['feedback'] : null
					];
				}, $ratings );

				return rest_ensure_response([
					'success' => true,
					'data'    => [
						'insights' => $data['insights'],
						'ratings'  => array_values( $ratings )
					],
				]);
			}
		} else {
			//  Check for any ratings & feedback
			$ratings = $this->get_insights_ratings();

			if ( ! empty( $ratings ) ) {
				//  Crosscheck with checked in list
				$keys = array_keys($ratings);

				foreach ( $keys as $rating_set_key ) {
					//  Delete ratings & feedback if already tracked
					if ( in_array( $rating_set_key, $this->get_checked_in_set_keys() ) ) {
						unset( $ratings[$rating_set_key] );
					}
				}

				//  Update ratings & feedback
				$this->update_insights_ratings($ratings);

				//  Clear tracked keys
				$this->update_checked_in_set_keys([]);
			}
		}

		$result = $this->generate_new_insights();

		if ( is_wp_error($result) || !$result['success'] ) {
			return rest_ensure_response($result);
		}

		$data = $result['data'];

		// Return the insights
		return rest_ensure_response([
			'success' => true,
			'data'    => [
				'insights'      => $data['insights'],
				'ratings'       => [],
			],
		]);
	}

	/**
	 * Get current insights ratings
	 * @param $request
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_current_insights_ratings( $request )
	{
		$data = $this->get_cached_insights();

		if (empty($data)) {
			return rest_ensure_response([
				'success'	=> true,
				'ratings'   => []
			]);
		}

		$insight_set_key = $this->get_insight_set_key($data['insights']);
		$ratings = $this->get_insight_ratings_set($insight_set_key);

		$ratings = array_map(function($rating) {
			return [
				'insight_id'    => $rating['insight']['id'],
				'score'         => $rating['score'],
				'skipped'       => $rating['skipped'],
				'feedback'      => !empty($rating['feedback']) ? $rating['feedback'] : null
			];
		}, $ratings);

		// Return the insights
		return rest_ensure_response([
			'success' => true,
			'data'    => $ratings
		]);
	}

	/**
	 * Rate an insight
	 * @param $request
	 * @return WP_Error|WP_REST_Response
	 */
	public function rate_insight( $request )
	{
		if ( empty($request['insight_id']) || (!is_numeric($request['score'])) ) {
			return new WP_Error( 'rest_invalid_param', 'Invalid parameter', [ 'status' => 400 ] );
		}

		$insight_id = $request['insight_id'];
		$score      = $request['score'];

		$insights_data  = $this->get_cached_insights();
		$insights       = $insights_data['insights'];

		$found_insight = null;

		//  Find insight
		foreach ($insights as $insight) {
			if ($insight['id'] === $insight_id) {
				$found_insight = $insight;
				break;
			}
		}

		//  Insight is missing somehow, unlikely to happen but let's bail
		if ( empty($found_insight) ) {
			return rest_ensure_response([
				'success' => true
			]);
		}

		//  Get the report data for the insight
		$report_id      = $found_insight['report_id'];
		$reports_data    = $insights_data['report_data'];

		$found_report_data = null;

		//  Find report data
		foreach ($reports_data as $report) {
			if ($report['name'] === $report_id) {
				$found_report_data = $report['data'];
				break;
			}
		}

		//  Report data is missing somehow. Again, unlikely to happen but let's bail
		if ( empty($found_report_data) ) {
			return rest_ensure_response([
				'success' => true
			]);
		}

		$insight_set_key = $this->get_insight_set_key($insights);
		$ratings_set = $this->get_insight_ratings_set($insight_set_key);

		$ratings_set[$found_insight['id']] = [
			'insight'       => $found_insight,
			'report_data'   => $found_report_data,
			'score'         => $score,
			'feedback'		=> null
		];

		//  Update ratings
		$this->update_insights_ratings_set($insight_set_key, $ratings_set);

		return rest_ensure_response([
			'success' => true
		]);
	}

	/**
	 * Process insights feedback
	 * @param $request
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function save_insight_feedback($request )
	{
		if ( empty($request['insight_id']) ) {
			return new WP_Error( 'rest_invalid_param', 'Invalid parameter', [ 'status' => 400 ] );
		}

		$insight_id = $request['insight_id'];

		$insights_data  = $this->get_cached_insights();
		$insights       = $insights_data['insights'];

		$found_insight = null;

		//  Find insight
		foreach ($insights as $insight) {
			if ($insight['id'] === $insight_id) {
				$found_insight = $insight;
				break;
			}
		}

		//  Insight is missing somehow, unlikely to happen but let's bail
		if ( empty($found_insight) ) {
			return rest_ensure_response([
				'success' => true
			]);
		}

		//  ... and get the insights set key
		$insight_set_key = $this->get_insight_set_key($insights);
		$ratings_set = $this->get_insight_ratings_set($insight_set_key);

		//  Ratings data is missing somehow. Unlikely to happen but let's bail
		if ( empty($ratings_set) ) {
			return rest_ensure_response([
				'success' => true
			]);
		}

		//  Insights data is missing somehow. Unlikely to happen but let's bail
		if ( empty($insights) ) {
			return rest_ensure_response([
				'success' => true
			]);
		}

		if ( $request['skipped'] === true ) {
			$ratings_set[$found_insight['id']]['skipped'] = true;
		} else {
			$ratings_set[$found_insight['id']]['skipped'] = false;
			$ratings_set[$found_insight['id']]['feedback'] = $request['message'];
		}

		//  All good, let's store the feedback
		$this->update_insights_ratings_set($insight_set_key, $ratings_set);

		return rest_ensure_response([
			'success' => true
		]);
	}

	/**
	 * Clear all ratings and feedback
	 * @return void
	 */
	public static function clear_ratings_and_feedback()
	{
		if ( is_multisite() ) {
			delete_site_option( self::INSIGHTS_RATING_OPTION_KEY );
			delete_site_option( self::INSIGHTS_FEEDBACK_CHECKIN_LIST_KEY );
		} else {
			delete_option( self::INSIGHTS_RATING_OPTION_KEY );
			delete_option( self::INSIGHTS_FEEDBACK_CHECKIN_LIST_KEY );
		}
	}

	/**
	 * @return false|mixed|null
	 */
	private function get_checked_in_set_keys()
	{
		return is_multisite() ?
			get_site_option(self::INSIGHTS_FEEDBACK_CHECKIN_LIST_KEY, []) :
			get_option(self::INSIGHTS_FEEDBACK_CHECKIN_LIST_KEY, []);
	}

	/**
	 * @param $keys
	 * @return void
	 */
	private function update_checked_in_set_keys($keys)
	{
		is_multisite() ?
			update_site_option( self::INSIGHTS_FEEDBACK_CHECKIN_LIST_KEY, $keys ) :
			update_option( self::INSIGHTS_FEEDBACK_CHECKIN_LIST_KEY, $keys );
	}

	/**
	 * Get insights ratings
	 * @return array|mixed
	 */
	private function get_insights_ratings()
	{
		return is_multisite() ?
			get_site_option( self::INSIGHTS_RATING_OPTION_KEY, [] ) :
			get_option( self::INSIGHTS_RATING_OPTION_KEY, [] );
	}

	/**
	 * Update insights ratings in DB
	 * @param $ratings
	 * @return bool
	 */
	private function update_insights_ratings($ratings)
	{
		return is_multisite() ?
			update_site_option( self::INSIGHTS_RATING_OPTION_KEY, $ratings ) :
			update_option( self::INSIGHTS_RATING_OPTION_KEY, $ratings );
	}

	/**
	 * Get insight ratings set
	 * @param $set_key
	 * @return array|mixed
	 */
	private function get_insight_ratings_set($set_key)
	{
		$ratings = $this->get_insights_ratings();
		return isset($ratings[$set_key]) ? $ratings[$set_key] : [];
	}

	/**
	 * Update insights ratings set
	 * @param $set_key
	 * @param $ratings
	 * @return bool
	 */
	private function update_insights_ratings_set($set_key, $ratings)
	{
		$all_ratings = $this->get_insights_ratings();
		$all_ratings[$set_key] = $ratings;

		return $this->update_insights_ratings($all_ratings);
	}

	/**
	 * Get cached insights
	 * @return mixed
	 */
	private function get_cached_insights()
	{
		return is_multisite() ?
			get_site_transient(self::INSIGHTS_TRANSIENT_KEY) :
			get_transient(self::INSIGHTS_TRANSIENT_KEY);
	}

	public function clear_cached_addons_for_ai()
	{
		return is_multisite() ?
			delete_site_transient(self::ADDONS_FOR_AI_TRANSIENT_KEY) :
			delete_transient(self::ADDONS_FOR_AI_TRANSIENT_KEY);
	}

	/**
	 * Get addons for AI
	 * @return array
	 */
	public static function get_addons_for_ai()
	{
		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$cached_addons_data = is_multisite() ?
			get_site_transient(self::ADDONS_FOR_AI_TRANSIENT_KEY) :
			get_transient(self::ADDONS_FOR_AI_TRANSIENT_KEY);

		if ( !empty($cached_addons_data) ) {
			return $cached_addons_data;
		}

		$addons_data       = \monsterinsights_get_addons();
		$parsed_addons     = array();
		$installed_plugins = \get_plugins();

		if ( ! is_array( $addons_data ) ) {
			$addons_data = array();
		}

		foreach ( $addons_data as $addons_type => $addons ) {
			foreach ( $addons as $addon ) {
				$slug = 'monsterinsights-' . $addon->slug;
				if ( 'monsterinsights-ecommerce' === $slug && 'm' === $slug[0] ) {
					$addon = monsterinsights_get_addon( $installed_plugins, $addons_type, $addon, $slug );
					if ( empty( $addon->installed ) ) {
						$slug  = 'ga-ecommerce';
						$addon = monsterinsights_get_addon( $installed_plugins, $addons_type, $addon, $slug );
					}
				} else {
					$addon = monsterinsights_get_addon( $installed_plugins, $addons_type, $addon, $slug );
				}
				$parsed_addons[ $addon->slug ] = $addon;
			}
		}

		$active_addons = array_filter( $parsed_addons, function( $addon ) {
			return $addon->active;
		});

		$addons_data = array_keys( $active_addons );

		is_multisite() ?
			set_site_transient( self::ADDONS_FOR_AI_TRANSIENT_KEY, $addons_data, WEEK_IN_SECONDS ) :
			set_transient( self::ADDONS_FOR_AI_TRANSIENT_KEY, $addons_data, WEEK_IN_SECONDS );

		return $addons_data;
	}

	/**
	 * Generate new insights from Relay API
	 * @return array|mixed
	 */
	public static function generate_new_insights()
	{
		$addons_keys = self::get_addons_for_ai();

		$api = new MonsterInsights_API_Request( 'analytics/ai/insights', [], 'GET' );

		$result = $api->request([
			'addons' => implode( ',', $addons_keys )
		]);

		if ( is_wp_error( $result ) ) {
			if ( 429 === $result->get_error_code() ) {
				$message = sprintf(
					esc_html__( 'You\'ve exceeded the number of daily AI Insights allowed. Please try again tomorrow. If you have questions please %1$scontact our support%2$s team.', 'google-analytics-premium' ),
					'<a target="_blank" href="' . monsterinsights_get_url( 'notice', 'ai-quota', 'https://www.monsterinsights.com/my-account/support/' ) . '">',
					'</a>'
				);

				return [
					'success' => false,
					'error'   => $message,
					'data'    => [],
				];
			}

			return [
				'success' => false,
				'error'   => $result->get_error_message(),
				'data'    => [],
			];
		}

		if ( empty( $result ) ) {
			return [
				'success' => false,
				'error'   => __('There was an issue connecting to the AI service, please try again.', 'google-analytics-premium'),
				'data'    => [],
			];
		}
		
		$current_read_count = get_option( self::AI_INSIGHTS_READ_OPTION_KEY, 0 ) + 1;
		update_option( self::AI_INSIGHTS_READ_OPTION_KEY, $current_read_count );

		$data = $result['data'];

		//  Cache Insights
		is_multisite() ?
			set_site_transient( self::INSIGHTS_TRANSIENT_KEY, $data, DAY_IN_SECONDS ) :
			set_transient( self::INSIGHTS_TRANSIENT_KEY, $data, DAY_IN_SECONDS );

		return [
			'success' => true,
			'data'    => $data,
		];
	}

	/**
	 * @param $insights
	 * @return string
	 */
	private function get_insight_set_key( $insights ): string
	{
		return array_reduce($insights, function ($carry, $insight) {
			if ( empty($carry) ) {
				return $insight['id'];
			}
			return $carry . '_' . $insight['id'];
		}, '');
	}
}

new MonsterInsights_AI_Insights();
