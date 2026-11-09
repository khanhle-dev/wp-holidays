<?php
/**
 * Hiển thị hero: tự chèn, shortcode và block.
 *
 * @package WPHolidays
 */

namespace WPHolidays;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	private const FX_COUNT = array(
		'petals'    => 22,
		'snow'      => 40,
		'confetti'  => 30,
		'lanterns'  => 10,
		'stars'     => 26,
		'fireworks' => 6,
	);

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_body_open', array( self::class, 'auto_insert' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_data' ) );
		add_shortcode( 'holiday_hero', array( self::class, 'shortcode' ) );
	}

	public static function register(): void {
		$url = plugin_dir_url( FILE ) . 'assets/';

		wp_register_style( 'wph-hero', $url . 'hero.css', array(), VERSION );
		wp_register_script( 'wph-hero', $url . 'hero.js', array(), VERSION, true );
		wp_register_script(
			'wph-block',
			$url . 'block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
			VERSION,
			true
		);

		register_block_type(
			'wp-holidays/hero',
			array(
				'api_version'     => 2,
				'editor_script'   => 'wph-block',
				'style'           => 'wph-hero',
				'attributes'      => array(
					'holiday' => array(
						'type'    => 'string',
						'default' => '',
					),
					'align'   => array( 'type' => 'string' ),
				),
				'supports'        => array(
					'align' => array( 'wide', 'full' ),
					'html'  => false,
				),
				'render_callback' => array( self::class, 'render_block' ),
			)
		);
	}

	/**
	 * Nạp CSS ngay trong <head> vào những ngày có hero để tránh nháy giao diện.
	 */
	public static function enqueue(): void {
		if ( self::preview() || Schedule::active() ) {
			wp_enqueue_style( 'wph-hero' );
		}
	}

	public static function editor_data(): void {
		$options = array();
		foreach ( Schedule::holidays() as $post ) {
			$options[] = array(
				'value' => $post->post_name,
				'label' => $post->post_title,
			);
		}
		wp_add_inline_script( 'wph-block', 'window.wphHolidays = ' . wp_json_encode( $options ) . ';', 'before' );
	}

	/**
	 * Ngày lễ quản trị viên đang xem trước qua ?wph_preview=ID.
	 */
	private static function preview(): ?WP_Post {
		// phpcs:ignore WordPress.Security.NonceVerification -- Chỉ đọc, đã kiểm tra quyền.
		$id = isset( $_GET['wph_preview'] ) ? absint( $_GET['wph_preview'] ) : 0;
		if ( ! $id || is_admin() ) {
			return null;
		}
		$post = get_post( $id );
		return $post && PostType::SLUG === $post->post_type && current_user_can( 'edit_post', $id ) ? $post : null;
	}

	public static function auto_insert(): void {
		$post = self::preview();

		if ( ! $post ) {
			$display = Admin::settings()['display'];
			$match   = 'all' === $display || ( 'front_page' === $display && is_front_page() );
			$post    = $match ? Schedule::active() : null;
		}

		if ( $post ) {
			echo self::html( $post ); // phpcs:ignore WordPress.Security.EscapeOutput -- html() đã escape.
		}
	}

	/**
	 * [holiday_hero], [holiday_hero holiday="trung-thu"]
	 * hoặc [holiday_hero]nội dung khi không có lễ[/holiday_hero].
	 */
	public static function shortcode( $atts, $content = '' ): string {
		$atts = shortcode_atts( array( 'holiday' => '' ), $atts, 'holiday_hero' );
		$post = self::resolve( $atts['holiday'] );

		return $post ? self::html( $post ) : do_shortcode( (string) $content );
	}

	public static function render_block( array $attributes ): string {
		$slug = $attributes['holiday'] ?? '';
		$post = self::resolve( $slug );

		// Trong trình soạn thảo, luôn có một hero để xem dù hôm nay không phải ngày lễ.
		if ( ! $post && ! $slug && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$post = Schedule::upcoming();
		}

		return $post ? self::html( $post, true ) : '';
	}

	private static function resolve( string $slug ): ?WP_Post {
		if ( $slug ) {
			return Schedule::by_slug( $slug );
		}

		// Khi xem trước, hero đã được chèn ở đầu trang nên không hiện thêm lần nữa.
		return self::preview() ? null : Schedule::active();
	}

	public static function html( WP_Post $post, bool $is_block = false ): string {
		wp_enqueue_style( 'wph-hero' );

		$c      = PostType::config( $post->ID );
		$preset = Themes::preset( $c['theme'] );
		$image  = (int) get_post_thumbnail_id( $post );
		$effect = $c['effect'] ? $c['effect'] : $preset['effect'];

		$settings = Admin::settings();

		$style = sprintf(
			'--wph-bg1:%s;--wph-bg2:%s;--wph-text:%s;--wph-accent:%s;--wph-overlay:%s;--wph-height:%d;--wph-container:%s',
			$c['bg1'] ? $c['bg1'] : $preset['bg1'],
			$c['bg2'] ? $c['bg2'] : $preset['bg2'],
			$c['text'] ? $c['text'] : $preset['text'],
			$c['accent'] ? $c['accent'] : $preset['accent'],
			$c['overlay'] / 100,
			$settings['min_height'],
			$settings['container_width'] ? (int) $settings['container_width'] . 'px' : 'none'
		);

		$attrs = array(
			'class' => 'wph-hero wph-hero--' . $c['theme'] . ( $settings['scroll_arrow'] ? ' wph-hero--has-scroll' : '' ) . ( $settings['scroll_snap'] ? ' wph-hero--snap' : '' ),
			'style' => $style,
		);

		$wrapper = $is_block
			? get_block_wrapper_attributes( $attrs )
			: sprintf( 'class="%s" style="%s"', esc_attr( $attrs['class'] ), esc_attr( $attrs['style'] ) );

		$today      = Schedule::today();
		$occurrence = Schedule::current_occurrence( $c, $today ) ?? Schedule::next_occurrence( $c, $today );
		$tokens     = $occurrence
			? array(
				'{year}'       => $occurrence['date']->format( 'Y' ),
				'{lunar_year}' => LunarCalendar::can_chi( Schedule::lunar_year( $c, $occurrence ) ),
			)
			: array();

		$heading = strtr( $c['heading'] ? $c['heading'] : $post->post_title, $tokens );

		$html = '<section ' . $wrapper . ' data-holiday="' . esc_attr( $post->post_name ) . '">';

		if ( $image ) {
			$html .= wp_get_attachment_image(
				$image,
				'full',
				false,
				array(
					'class'         => 'wph-hero__media',
					'alt'           => '',
					'sizes'         => '100vw',
					'loading'       => 'eager',
					'fetchpriority' => 'high',
				)
			);
			$html .= '<div class="wph-hero__overlay" aria-hidden="true"></div>';
		} else {
			$html .= Themes::motif( $preset['motif'] );
		}

		$html .= self::effect( $effect );
		$html .= '<div class="wph-hero__container"><div class="wph-hero__content">';

		if ( $c['eyebrow'] ) {
			$html .= '<p class="wph-hero__eyebrow">' . esc_html( strtr( $c['eyebrow'], $tokens ) ) . '</p>';
		}

		$html .= '<h2 class="wph-hero__title">' . esc_html( $heading ) . '</h2>';

		if ( $c['subheading'] ) {
			$html .= '<p class="wph-hero__text">' . nl2br( esc_html( strtr( $c['subheading'], $tokens ) ) ) . '</p>';
		}

		if ( $c['button_text'] && $c['button_url'] ) {
			$html .= '<a class="wph-hero__button" href="' . esc_url( $c['button_url'] ) . '">' . esc_html( $c['button_text'] ) . '</a>';
		}

		$html .= '</div></div>';

		if ( $settings['scroll_arrow'] || $settings['scroll_snap'] ) {
			wp_enqueue_script( 'wph-hero' );
		}

		if ( $settings['scroll_arrow'] ) {
			$html .= sprintf(
				'<button type="button" class="wph-hero__scroll" aria-label="%s"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m5 9 7 7 7-7"/></svg></button>',
				esc_attr__( 'Cuộn xuống phần tiếp theo', 'wp-holidays' )
			);
		}

		$html .= '</section>';

		return apply_filters( 'wph_hero_html', $html, $post, $c );
	}

	/**
	 * Các hạt hiệu ứng, vị trí rải đều theo công thức cố định.
	 */
	private static function effect( string $effect ): string {
		if ( empty( self::FX_COUNT[ $effect ] ) ) {
			return '';
		}

		$items = '';
		for ( $i = 1; $i <= self::FX_COUNT[ $effect ]; $i++ ) {
			$duration = 9 + ( $i * 7 ) % 8;
			// Lệch pha theo tỉ lệ vàng để các hạt không dồn thành cụm.
			$phase  = fmod( $i * 0.618034, 1 );
			$items .= sprintf(
				'<i style="--x:%d%%;--y:%d%%;--d:-%.1fs;--t:%ds;--s:%.2f"></i>',
				( $i * 37 ) % 100,
				( $i * 61 ) % 100,
				$phase * $duration,
				$duration,
				0.6 + ( ( $i * 13 ) % 9 ) / 10
			);
		}

		return '<div class="wph-fx wph-fx--' . esc_attr( $effect ) . '" aria-hidden="true">' . $items . '</div>';
	}
}
