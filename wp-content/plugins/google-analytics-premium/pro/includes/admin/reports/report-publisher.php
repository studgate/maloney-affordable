<?php
/**
 * Publisher Report
 *
 * Ensures all of the reports have a uniform class with helper functions.
 *
 * @since 6.0.0
 *
 * @package MonsterInsights
 * @subpackage Reports
 * @author  Chris Christoff
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_Publisher extends MonsterInsights_Report {

	public $title;
	public $class = 'MonsterInsights_Report_Publisher';
	public $name = 'publisher';
	public $version = '1.0.0';
	public $level = 'plus';

	/**
	 * Primary class constructor.
	 *
	 * @access public
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->title = __( 'Engagement', 'google-analytics-premium' );
		parent::__construct();
	}

	/**
	 * Prepare report-specific data for output.
	 *
	 * @param array $data The data from the report before it gets sent to the frontend.
	 *
	 * @return mixed
	 */
	public function prepare_report_data( $data ) {

		// Prepare age colors.
		if ( ! empty( $data['data']['age'] ) ) {
			foreach ( $data['data']['age']['graph']['colors'] as $color_key => $color ) {
				$data['data']['age']['graph']['colors'][ $color_key ] = str_replace( '"', '', $color );
			}
			foreach ( $data['data']['age']['graph']['labels'] as $label_key => $label ) {
				$data['data']['age']['graph']['labels'][ $label_key ] = str_replace( '"', '', $label );
			}
		}
		// Prepare gender colors.
		if ( ! empty( $data['data']['gender'] ) ) {
			foreach ( $data['data']['gender']['graph']['colors'] as $color_key => $color ) {
				$data['data']['gender']['graph']['colors'][ $color_key ] = str_replace( '"', '', $color );
			}
			foreach ( $data['data']['gender']['graph']['labels'] as $label_key => $label ) {
				$data['data']['gender']['graph']['labels'][ $label_key ] = str_replace( '"', '', $label );
			}
		}

		// ESC_HTML on outbound links.
		if ( ! empty( $data['data']['outboundlinks'] ) ) {
			foreach ( $data['data']['outboundlinks'] as $link_key => $outboundlink ) {
				if ( ! isset( $data['data']['outboundlinks'][ $link_key ]['title'] ) ) {
					continue;
				}
				if ( ! isset( $data['data']['outboundlinks'][ $link_key ]['title'][0] ) ) {
					continue;
				}
				$data['data']['outboundlinks'][ $link_key ]['title'][0] = esc_html( $outboundlink['title'][0] );
			}
		}

		// ESC_HTML on affiliate links.
		if ( ! empty( $data['data']['affiliatelinks'] ) ) {
			foreach ( $data['data']['affiliatelinks'] as $link_key => $affiliatelink ) {
				if ( ! isset( $data['data']['affiliatelinks'][ $link_key ]['title'] ) ) {
					continue;
				}
				if ( ! isset( $data['data']['affiliatelinks'][ $link_key ]['title'][0] ) ) {
					continue;
				}
				$data['data']['affiliatelinks'][ $link_key ]['title'][0] = esc_html( $affiliatelink['title'][0] );
			}
		}

		if ( ! empty( $data['data'] ) ) {
			$data['data']['galinks'] = array(
				'landingpages'   => $this->get_ga_report_url(
					'all-pages-and-screens',
					$data['data'],
					'_r.explorerCard..columnFilters={"event":"session_start"}&_r.explorerCard..selmet=["eventCount","activeUsers"]&_r.explorerCard..sortKey=eventCount'
				),
				'exitpages'      => $this->get_ga_report_url(  '', $data['data'] ),
				'outboundlinks'  => $this->get_ga_report_url(
					'events-overview',
					$data['data'],
					'_r..dimension-value={"dimension":"eventName","value":"click"}&_u..comparisons=[{"name":"Outbound includes true; Is Affiliate Link includes false","filters":[{"fieldName":"customParamDimension:14","expressionList":["true"],"isCaseSensitive":true},{"fieldName":"customParamDimension:9","expressionList":["false"],"isCaseSensitive":true}]}]',
					'dashboard'
				),
				'affiliatelinks' => $this->get_ga_report_url(
					'events-overview',
					$data['data'],
					'_r..dimension-value={"dimension":"eventName","value":"click"}&_u..comparisons=[{"name":"Outbound includes true; Is Affiliate Link includes false","filters":[{"fieldName":"customParamDimension:14","expressionList":["true"],"isCaseSensitive":true},{"fieldName":"customParamDimension:15","expressionList":["true"],"isCaseSensitive":true}]}]',
					'dashboard'
				),
				'downloadlinks'  => $this->get_ga_report_url(
					'events-overview',
					$data['data'],
					'_r..dimension-value={"dimension":"eventName","value":"file_download"}',
					'dashboard'
				),
				'interest'       => $this->get_ga_report_url(
					'user-demographics-detail',
					$data['data'],
					'_u..nav=maui&_r.explorerCard..selmet=["activeUsers"]&_r.explorerCard..seldim=["brandingInterest"]&collectionId=user',
					'explorer'
				),
			);
		}

		return $data;
	}
}
