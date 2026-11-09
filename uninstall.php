<?php
/**
 * Xóa dữ liệu của plugin khi gỡ cài đặt.
 *
 * @package WPHolidays
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wph_ids = get_posts(
	array(
		'post_type'        => 'wph_holiday',
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	)
);

foreach ( $wph_ids as $wph_id ) {
	wp_delete_post( $wph_id, true );
}

delete_option( 'wph_seeded' );
delete_option( 'wph_settings' );
delete_transient( 'wph_active' );
