<?php

add_action('init', function () {

	if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/reports.php';
		new MonsterInsights_Admin_Pro_Reports();

		// Email summaries related classes
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/emails/summaries-infoblocks.php';
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/emails/summaries.php';
		new MonsterInsights_Email_Summaries();

		// SharedCounts functionality.
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/admin/sharedcount.php';

		// Include notification events of pro version
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/notifications/notification-events.php';

		// Load API classes
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/api/class-monsterinsights-api-error.php';
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/api/class-monsterinsights-api.php';
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/api/class-monsterinsights-api-reports.php';
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/api/class-monsterinsights-api-tracking.php';

		// Pro-only API
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/api/class-monsterinsights-api-ads.php';

		// Load Google Ads admin classes
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/ppc/google/class-monsterinsights-google-ads.php';
	}

	if ( is_admin() ) {

		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/dashboard-widget.php';
		new MonsterInsights_Dashboard_Widget_Pro();

		// Load the Welcome class.
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/welcome.php';

		if ( isset( $_GET['page'] ) && 'monsterinsights-onboarding' === $_GET['page'] ) { // phpcs:ignore -- CSRF ok, input var ok.
			// Only load the Onboarding wizard if the required parameter is present.
			require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/onboarding-wizard.php';
		}

		//  Common Site Health logic
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/admin/wp-site-health.php';

		//  Pro-only Site Health logic
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/wp-site-health.php';

		if (
			class_exists( 'MonsterInsights_eCommerce' ) &&
			file_exists( WP_PLUGIN_DIR . '/monsterinsights-user-journey/monsterinsights-user-journey.php' ) &&
			! class_exists( 'MonsterInsights_User_Journey' )
		) {
			// Initialize User Journey
			require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/user-journey/init.php';
		}
	}

	//  Gtag selector
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/gtag-selector.php';

	//  AI Insights
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/ai/class-monsterinsights-ai-insights.php';

	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/frontend/class-frontend.php';

	// Popular posts.
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/popular-posts/class-popular-posts-themes.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/popular-posts/class-popular-posts.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/popular-posts/class-popular-posts-helper.php';
	// Pro popular posts specific.
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/popular-posts/class-popular-posts-inline.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/popular-posts/class-popular-posts-cache.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/popular-posts/class-popular-posts-widget.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/popular-posts/class-popular-posts-widget-sidebar.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/popular-posts/class-popular-posts-ajax.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/popular-posts/class-popular-posts-ga.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/popular-posts/class-popular-posts-products.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/popular-posts/class-popular-posts-products-sidebar.php';
	// Pro Gutenberg blocks.
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/gutenberg/monsterinsights-stats-block.php';
	// Privacy Guard.
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/frontend/privacy-guard.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/gutenberg/frontend.php';
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/connect.php';

	// Custom conversion tracking.
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/custom-conversion-tracking.php';

	// Run hook to load MonsterInsights addons.
	do_action( 'monsterinsights_load_plugins' ); // the updater class for each addon needs to be instantiated via `monsterinsights_updater`

	if ( !is_admin() ) {
		// Load PPC Core
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/ppc/class-monsterinsights-ppc-tracking-core.php';
	}
}, 0 );

add_action( 'et_builder_ready', function() {
	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/frontend/divi-button-module.php';

	new MonsterInsights_Divi_Button_Module();

	require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/frontend/divi-image-module.php';

	new MonsterInsights_Divi_Image_Module();
}, 200 );
