<?php

/**
 * Add notification when WooCommerce, Easy Digital Downloads, Memberpress, LifterLMS plugins are active but Ecommerce Tracking is disabled or Ecommerce Addon not available
 * Recurrence: 20 Days
 *
 * @since 7.12.3
 */
final class MonsterInsights_Notification_To_Enable_Ecommerce_Tracking extends MonsterInsights_Notification_Event {

	public $notification_id = 'monsterinsights_notification_to_enable_ecommerce_tracking';
	public $notification_interval = 20; // in days
	public $notification_type = array( 'master', 'pro' );
	public $notification_category = 'insight';
	public $notification_priority = 3;

	/**
	 * Build Notification
	 *
	 * @return array $notification notification is ready to add
	 *
	 * @since 7.12.3
	 */
	public function prepare_notification_data( $notification ) {

		if ( !class_exists('MonsterInsights_eCommerce') && (
				class_exists( 'WooCommerce' ) ||
				class_exists( 'Easy_Digital_Downloads' ) ||
				( defined( 'MEPR_VERSION' ) && version_compare( MEPR_VERSION, '1.3.43', '>' ) ) ||
				( function_exists( 'LLMS' ) && version_compare( LLMS()->version, '3.32.0', '>=' ) )
			) ) {

			$notification['title'] = __( 'Enable our eCommerce addon to instantly track sales and revenue in Google Analytics', 'google-analytics-premium' );
			/* translators: Placeholders add a link to an article. */
			$notification['content'] = sprintf( __( 'The MonsterInsights eCommerce addon makes it effortless to add Google Analytics Enhanced eCommerce tracking to your WordPress store whether you are using WooCommerce, MemberPress, LifterLMS or Easy Digital Downloads.<br><br>Enhanced eCommerce tracking lets you collect more data and gives you detailed insight into eCommerce engagement. %1$sIn this article%2$s, we will show you how to enable Enhanced eCommerce in WordPress with MonsterInsights.', 'google-analytics-premium' ), '<a href="' . $this->build_external_link( 'https://www.monsterinsights.com/docs/how-to-enable-enhanced-ecommerce-in-wordpress/' ) . '" target="_blank">', '</a>' );
			$notification['btns']    = array(
				"activate_addon" => array(
					'url'  => $this->get_view_url( 'monsterinsights-addon-ecommerce', 'monsterinsights_settings', 'addons' ),
					'text' => __( 'Activate Addon', 'google-analytics-premium' ),
				)
			);

			return $notification;
		}

		return false;
	}

}

// initialize the class
new MonsterInsights_Notification_To_Enable_Ecommerce_Tracking();
