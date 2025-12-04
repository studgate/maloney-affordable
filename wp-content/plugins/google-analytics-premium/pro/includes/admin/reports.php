<?php

/**
 * Pro Admin features.
 *
 * Adds Pro Reporting features.
 *
 * @since 6.0.0
 *
 * @package MonsterInsights Dimensions
 * @subpackage Reports
 * @author  Chris Christoff
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Admin_Pro_Reports {

	/**
	 * Primary class constructor.
	 *
	 * @access public
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->load_reports();
	}

	public function load_reports() {
		$overview_report = new MonsterInsights_Report_Overview();
		MonsterInsights()->reporting->add_report( $overview_report );

		$site_summary = new MonsterInsights_Report_Site_Summary();
		MonsterInsights()->reporting->add_report( $site_summary );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-country.php';
		$country_report = new MonsterInsights_Report_Countries();
		MonsterInsights()->reporting->add_report( $country_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-publisher.php';
		$publisher_report = new MonsterInsights_Report_Publisher();
		MonsterInsights()->reporting->add_report( $publisher_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-ecommerce.php';
		$ecommerce_report = new MonsterInsights_Report_eCommerce();
		MonsterInsights()->reporting->add_report( $ecommerce_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-queries.php';
		$queries_report = new MonsterInsights_Report_Queries();
		MonsterInsights()->reporting->add_report( $queries_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-dimensions.php';
		$dimensions_report = new MonsterInsights_Report_Dimensions();
		MonsterInsights()->reporting->add_report( $dimensions_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-forms.php';
		$forms_report = new MonsterInsights_Report_Forms();
		MonsterInsights()->reporting->add_report( $forms_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-realtime.php';
		$realtime_report = new MonsterInsights_Report_RealTime();
		MonsterInsights()->reporting->add_report( $realtime_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-year-in-review.php';
		$year_in_review = new MonsterInsights_Report_YearInReview();
		MonsterInsights()->reporting->add_report( $year_in_review );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-popularposts.php';
		$popular_posts = new MonsterInsights_Report_PopularPosts();
		MonsterInsights()->reporting->add_report( $popular_posts );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-site-speed.php';
		$site_speed = new MonsterInsights_Report_SiteSpeed();
		MonsterInsights()->reporting->add_report( $site_speed );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-site-speed-mobile.php';
		$site_speed_mobile = new MonsterInsights_Report_SiteSpeed_Mobile();
		MonsterInsights()->reporting->add_report( $site_speed_mobile );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-summaries.php';
		$summaries = new MonsterInsights_Report_Summaries();
		MonsterInsights()->reporting->add_report( $summaries );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-media.php';
		$media_report = new MonsterInsights_Report_Media();
		MonsterInsights()->reporting->add_report( $media_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-traffic-overview.php';
		$traffic_overview_report = new MonsterInsights_Report_Traffic_Overview();
		MonsterInsights()->reporting->add_report( $traffic_overview_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-traffic-landing-pages.php';
		$traffic_landing_pages_report = new MonsterInsights_Report_Traffic_Landing_Pages();
		MonsterInsights()->reporting->add_report( $traffic_landing_pages_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-traffic-technology.php';
		$traffic_traffic_technology_report = new MonsterInsights_Report_Traffic_Technology();
		MonsterInsights()->reporting->add_report( $traffic_traffic_technology_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-traffic-campaign.php';
		$traffic_traffic_campaign_report = new MonsterInsights_Report_Traffic_Campaign();
		MonsterInsights()->reporting->add_report( $traffic_traffic_campaign_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-traffic-source-medium.php';
		$traffic_source_medium_report = new MonsterInsights_Report_Traffic_Source_Medium();
		MonsterInsights()->reporting->add_report( $traffic_source_medium_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-traffic-social.php';
		$traffic_social_report = new MonsterInsights_Report_Traffic_Social();
		MonsterInsights()->reporting->add_report( $traffic_social_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-traffic-ai.php';
		$traffic_ai_report = new MonsterInsights_Report_Traffic_AI();
		MonsterInsights()->reporting->add_report( $traffic_ai_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-ecommerce-funnel.php';
		$ecommerce_funnel_report = new MonsterInsights_Report_eCommerce_Funnel();
		MonsterInsights()->reporting->add_report( $ecommerce_funnel_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-ecommerce-coupons.php';
		$ecommerce_coupons_report = new MonsterInsights_Report_eCommerce_Coupons();
		MonsterInsights()->reporting->add_report( $ecommerce_coupons_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-cart-abandonment.php';
		$ecommerce_cart_abandonment_report = new MonsterInsights_Report_Cart_Abandonment();
		MonsterInsights()->reporting->add_report( $ecommerce_cart_abandonment_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-ecommerce-product-feed.php';
		$ecommerce_product_feed_report = new MonsterInsights_Report_Ecommerce_Product_Feed();
		MonsterInsights()->reporting->add_report( $ecommerce_product_feed_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-engagement-pages.php';
		$engagement_pages_report = new MonsterInsights_Report_Engagement_Pages();
		MonsterInsights()->reporting->add_report( $engagement_pages_report );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-site-insights.php';
		$site_insights = new MonsterInsights_Report_SiteInsights();
		MonsterInsights()->reporting->add_report( $site_insights );

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports/report-custom-events.php';
		$custom_events = new MonsterInsights_Report_Custom_Events();
		MonsterInsights()->reporting->add_report( $custom_events );
	}
}
