<?php

namespace WPFormsUserJourney;

use WPForms_DB;

/**
 * The User Journey DB stores records in a custom database.
 *
 * @since 1.0.0
 */
class DB extends WPForms_DB {

	/**
	 * Primary key (unique field) for the database table.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $primary_key = 'id';

	/**
	 * Database type identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $type = 'user_journey';

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( method_exists( get_parent_class( $this ), '__construct' ) ) {
			parent::__construct();
		}

		$this->table_name = self::get_table_name();
	}

	/**
	 * Get the DB table name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_table_name() {

		global $wpdb;

		return $wpdb->prefix . 'wpforms_user_journey';
	}

	/**
	 * Get table columns.
	 *
	 * @since 1.0.0
	 */
	public function get_columns() {

		return [
			'id'         => '%d',
			'entry_id'   => '%d',
			'form_id'    => '%d',
			'post_id'    => '%d',
			'url'        => '%s',
			'parameters' => '%s',
			'external'   => '%d',
			'title'      => '%s',
			'duration'   => '%d',
			'step'       => '%d',
			'date'       => '%s',
		];
	}

	/**
	 * Default column values.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_column_defaults() {

		return [
			'entry_id'   => '',
			'form_id'    => '',
			'post_id'    => '',
			'url'        => '',
			'parameters' => '',
			'external'   => '',
			'title'      => '',
			'duration'   => '',
			'step'       => '',
			'date'       => gmdate( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * Get rows from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args  Optional args.
	 * @param bool  $count Flag to return the count instead of results.
	 *
	 * @return array|int
	 */
	public function get_rows( $args = [], $count = false ) {

		global $wpdb;

		$defaults = [
			'number'   => 999,
			'offset'   => 0,
			'id'       => 0,
			'entry_id' => 0,
			'form_id'  => 0,
			'post_id'  => 0,
			'orderby'  => 'id',
			'order'    => 'ASC',
		];

		$args = wp_parse_args( $args, $defaults );

		if ( $args['number'] < 1 ) {
			$args['number'] = PHP_INT_MAX;
		}

		$where = $this->build_where(
			$args,
			[ 'id', 'entry_id', 'form_id', 'post_id' ]
		);

		// Orderby.
		$args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? $this->primary_key : $args['orderby'];

		// Offset.
		$args['offset'] = absint( $args['offset'] );

		// Number.
		$args['number'] = absint( $args['number'] );

		// Order.
		if ( 'ASC' === strtoupper( $args['order'] ) ) {
			$args['order'] = 'ASC';
		} else {
			$args['order'] = 'DESC';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count === true ) {
			$results = absint( $wpdb->get_var( "SELECT COUNT( $this->primary_key ) FROM $this->table_name $where;" ) );
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM $this->table_name $where ORDER BY {$args['orderby']} {$args['order']} LIMIT {$args['offset']}, {$args['number']};"
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results;
	}
}
