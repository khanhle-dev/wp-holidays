<?php
/**
 * Loại nội dung "Ngày lễ" và màn hình nhập liệu.
 *
 * Trạng thái Đã xuất bản nghĩa là ngày lễ đang bật, Bản nháp nghĩa là đang tắt.
 *
 * @package WPHolidays
 */

namespace WPHolidays;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class PostType {

	public const SLUG = 'wph_holiday';
	public const META = '_wph_config';

	private const DEFAULTS = array(
		'calendar'    => 'solar',
		'day'         => 1,
		'month'       => 1,
		'before'      => 0,
		'after'       => 0,
		'priority'    => 10,
		'eyebrow'     => '',
		'heading'     => '',
		'subheading'  => '',
		'button_text' => '',
		'button_url'  => '',
		'theme'       => 'national',
		'effect'      => '',
		'bg1'         => '',
		'bg2'         => '',
		'text'        => '',
		'accent'      => '',
		'overlay'     => 55,
	);

	public static function init(): void {
		add_action( 'after_setup_theme', array( self::class, 'thumbnails' ), 20 );
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'add_meta_boxes_' . self::SLUG, array( self::class, 'meta_boxes' ) );
		add_action( 'save_post_' . self::SLUG, array( self::class, 'save' ), 5, 2 );
		add_filter( 'manage_' . self::SLUG . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . self::SLUG . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		add_action( 'pre_get_posts', array( self::class, 'default_order' ) );
	}

	public static function thumbnails(): void {
		add_theme_support( 'post-thumbnails', array( self::SLUG ) );
	}

	public static function register(): void {
		register_post_type(
			self::SLUG,
			array(
				'labels'       => array(
					'name'                  => __( 'Ngày lễ', 'wp-holidays' ),
					'singular_name'         => __( 'Ngày lễ', 'wp-holidays' ),
					'menu_name'             => __( 'Hero ngày lễ', 'wp-holidays' ),
					'all_items'             => __( 'Tất cả ngày lễ', 'wp-holidays' ),
					'add_new'               => __( 'Thêm ngày lễ', 'wp-holidays' ),
					'add_new_item'          => __( 'Thêm ngày lễ mới', 'wp-holidays' ),
					'edit_item'             => __( 'Sửa ngày lễ', 'wp-holidays' ),
					'search_items'          => __( 'Tìm ngày lễ', 'wp-holidays' ),
					'not_found'             => __( 'Không có ngày lễ nào', 'wp-holidays' ),
					'not_found_in_trash'    => __( 'Thùng rác trống', 'wp-holidays' ),
					'featured_image'        => __( 'Ảnh nền hero', 'wp-holidays' ),
					'set_featured_image'    => __( 'Chọn ảnh nền', 'wp-holidays' ),
					'remove_featured_image' => __( 'Bỏ ảnh nền', 'wp-holidays' ),
					'use_featured_image'    => __( 'Dùng làm ảnh nền', 'wp-holidays' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => array( 'title', 'thumbnail' ),
			)
		);
	}

	public static function config( int $post_id ): array {
		$saved = get_post_meta( $post_id, self::META, true );
		return array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : array() );
	}

	public static function sanitize( array $raw ): array {
		$raw = array_intersect_key( array_merge( self::DEFAULTS, $raw ), self::DEFAULTS );

		$raw['calendar'] = 'lunar' === $raw['calendar'] ? 'lunar' : 'solar';
		$raw['month']    = self::clamp( $raw['month'], 1, 12 );

		// Năm 2024 là năm nhuận nên 29/2 vẫn hợp lệ. Lịch âm có tối đa 30 ngày.
		$max_day    = 'lunar' === $raw['calendar'] ? 30 : (int) gmdate( 't', gmmktime( 0, 0, 0, $raw['month'], 1, 2024 ) );
		$raw['day'] = self::clamp( $raw['day'], 1, $max_day );

		$raw['before']   = self::clamp( $raw['before'], 0, 60 );
		$raw['after']    = self::clamp( $raw['after'], 0, 60 );
		$raw['priority'] = self::clamp( $raw['priority'], 0, 100 );
		$raw['overlay']  = self::clamp( $raw['overlay'], 0, 100 );

		$raw['eyebrow']     = sanitize_text_field( $raw['eyebrow'] );
		$raw['heading']     = sanitize_text_field( $raw['heading'] );
		$raw['subheading']  = sanitize_textarea_field( $raw['subheading'] );
		$raw['button_text'] = sanitize_text_field( $raw['button_text'] );
		$raw['button_url']  = esc_url_raw( $raw['button_url'] );

		$raw['theme']  = isset( Themes::presets()[ $raw['theme'] ] ) ? $raw['theme'] : 'national';
		$raw['effect'] = isset( Themes::effects()[ $raw['effect'] ] ) ? $raw['effect'] : '';

		foreach ( array( 'bg1', 'bg2', 'text', 'accent' ) as $color ) {
			$raw[ $color ] = (string) sanitize_hex_color( $raw[ $color ] );
		}

		return $raw;
	}

	private static function clamp( $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}

	public static function meta_boxes(): void {
		add_meta_box( 'wph_schedule', __( 'Thời gian', 'wp-holidays' ), array( self::class, 'render_schedule' ), self::SLUG, 'normal', 'high' );
		add_meta_box( 'wph_content', __( 'Nội dung hero', 'wp-holidays' ), array( self::class, 'render_content' ), self::SLUG, 'normal', 'high' );
		add_meta_box( 'wph_design', __( 'Giao diện', 'wp-holidays' ), array( self::class, 'render_design' ), self::SLUG, 'normal', 'default' );
	}

	private static function row( string $label, string $control, string $help = '' ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo $control; // phpcs:ignore WordPress.Security.EscapeOutput -- Các control đã được escape khi tạo.
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function input( string $name, $value, string $type = 'text', string $attrs = 'class="large-text"' ): string {
		return sprintf( '<input type="%s" name="wph[%s]" value="%s" aria-label="%s" %s>', esc_attr( $type ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( $name ), $attrs );
	}

	private static function select( string $name, string $current, array $options ): string {
		$html = sprintf( '<select name="wph[%s]">', esc_attr( $name ) );
		foreach ( $options as $value => $label ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( (string) $value ), selected( (string) $value, $current, false ), esc_html( $label ) );
		}
		return $html . '</select>';
	}

	private static function number( string $name, $value, int $min, int $max ): string {
		return self::input( $name, $value, 'number', sprintf( 'class="small-text" min="%d" max="%d"', $min, $max ) );
	}

	public static function render_schedule( WP_Post $post ): void {
		$c = self::config( $post->ID );
		wp_nonce_field( 'wph_save', 'wph_nonce' );

		echo '<table class="form-table">';
		self::row(
			__( 'Loại lịch', 'wp-holidays' ),
			self::select(
				'calendar',
				$c['calendar'],
				array(
					'solar' => __( 'Dương lịch', 'wp-holidays' ),
					'lunar' => __( 'Âm lịch', 'wp-holidays' ),
				)
			)
		);
		self::row(
			__( 'Ngày / tháng', 'wp-holidays' ),
			self::number( 'day', $c['day'], 1, 31 ) . ' / ' . self::number( 'month', $c['month'], 1, 12 ),
			__( 'Ngày âm lịch được đổi tự động sang dương lịch mỗi năm. Tháng nhuận được tính theo tháng thường.', 'wp-holidays' )
		);
		self::row( __( 'Hiện trước (ngày)', 'wp-holidays' ), self::number( 'before', $c['before'], 0, 60 ) );
		self::row( __( 'Hiện sau (ngày)', 'wp-holidays' ), self::number( 'after', $c['after'], 0, 60 ) );
		self::row( __( 'Độ ưu tiên', 'wp-holidays' ), self::number( 'priority', $c['priority'], 0, 100 ), __( 'Số lớn hơn sẽ được hiển thị khi nhiều ngày lễ trùng thời gian.', 'wp-holidays' ) );
		echo '</table>';
	}

	public static function render_content( WP_Post $post ): void {
		$c = self::config( $post->ID );

		echo '<table class="form-table">';
		self::row( __( 'Dòng nhỏ phía trên', 'wp-holidays' ), self::input( 'eyebrow', $c['eyebrow'] ) );
		self::row( __( 'Tiêu đề lớn', 'wp-holidays' ), self::input( 'heading', $c['heading'] ), __( 'Có thể dùng {year} (năm dương lịch) và {lunar_year} (can chi, ví dụ Bính Ngọ). Để trống sẽ dùng tên ngày lễ.', 'wp-holidays' ) );
		self::row( __( 'Mô tả', 'wp-holidays' ), sprintf( '<textarea name="wph[subheading]" rows="3" class="large-text" aria-label="subheading">%s</textarea>', esc_textarea( $c['subheading'] ) ) );
		self::row( __( 'Chữ trên nút', 'wp-holidays' ), self::input( 'button_text', $c['button_text'] ) );
		self::row( __( 'Liên kết của nút', 'wp-holidays' ), self::input( 'button_url', $c['button_url'], 'url' ), __( 'Nút chỉ hiện khi có đủ chữ và liên kết.', 'wp-holidays' ) );
		echo '</table>';
	}

	public static function render_design( WP_Post $post ): void {
		$c = self::config( $post->ID );

		echo '<table class="form-table">';
		self::row( __( 'Mẫu giao diện', 'wp-holidays' ), self::select( 'theme', $c['theme'], wp_list_pluck( Themes::presets(), 'label' ) ) );
		self::row( __( 'Hiệu ứng', 'wp-holidays' ), self::select( 'effect', $c['effect'], array( '' => __( 'Theo mẫu giao diện', 'wp-holidays' ) ) + Themes::effects() ) );

		$colors = array(
			'bg1'    => __( 'Màu nền 1', 'wp-holidays' ),
			'bg2'    => __( 'Màu nền 2', 'wp-holidays' ),
			'text'   => __( 'Màu chữ', 'wp-holidays' ),
			'accent' => __( 'Màu nhấn', 'wp-holidays' ),
		);
		foreach ( $colors as $key => $label ) {
			self::row( $label, self::input( $key, $c[ $key ], 'text', 'class="wph-color"' ), 'bg1' === $key ? __( 'Để trống các ô màu để dùng màu của mẫu giao diện.', 'wp-holidays' ) : '' );
		}

		self::row( __( 'Độ phủ màu trên ảnh nền (%)', 'wp-holidays' ), self::number( 'overlay', $c['overlay'], 0, 100 ), __( 'Chỉ áp dụng khi có ảnh nền. Tăng lên nếu chữ khó đọc.', 'wp-holidays' ) );
		echo '</table>';
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['wph_nonce'], $_POST['wph'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wph_nonce'] ), 'wph_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- PostType::sanitize() xử lý từng trường.
		update_post_meta( $post_id, self::META, self::sanitize( (array) wp_unslash( $_POST['wph'] ) ) );
	}

	public static function preview_url( int $post_id ): string {
		return add_query_arg( 'wph_preview', $post_id, home_url( '/' ) );
	}

	public static function columns( array $columns ): array {
		unset( $columns['date'] );
		$columns['wph_date']   = __( 'Ngày', 'wp-holidays' );
		$columns['wph_next']   = __( 'Lần tới', 'wp-holidays' );
		$columns['wph_window'] = __( 'Khung hiển thị', 'wp-holidays' );
		return $columns;
	}

	public static function column( string $column, int $post_id ): void {
		$c = self::config( $post_id );

		if ( 'wph_date' === $column ) {
			echo esc_html( self::date_label( $c ) );
		} elseif ( 'wph_next' === $column ) {
			$next = Schedule::next_occurrence( $c, Schedule::today() );
			echo esc_html( $next ? $next['date']->format( 'd/m/Y' ) : '—' );
		} elseif ( 'wph_window' === $column ) {
			/* translators: 1: số ngày trước, 2: số ngày sau */
			echo esc_html( sprintf( __( 'Trước %1$d, sau %2$d ngày', 'wp-holidays' ), $c['before'], $c['after'] ) );
		}
	}

	public static function date_label( array $c ): string {
		return sprintf( '%d/%d %s', $c['day'], $c['month'], 'lunar' === $c['calendar'] ? __( 'âm lịch', 'wp-holidays' ) : __( 'dương lịch', 'wp-holidays' ) );
	}

	public static function row_actions( array $actions, WP_Post $post ): array {
		if ( self::SLUG === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
			if ( 'trash' !== $post->post_status ) {
				$actions['wph_preview'] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( self::preview_url( $post->ID ) ), esc_html__( 'Xem trước', 'wp-holidays' ) );
			}
		}
		return $actions;
	}

	public static function default_order( \WP_Query $query ): void {
		if ( is_admin() && $query->is_main_query() && self::SLUG === $query->get( 'post_type' ) && ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		}
	}
}
