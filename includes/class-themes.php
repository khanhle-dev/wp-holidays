<?php
/**
 * Bộ giao diện dựng sẵn và họa tiết SVG của hero.
 *
 * @package WPHolidays
 */

namespace WPHolidays;

defined( 'ABSPATH' ) || exit;

final class Themes {

	/**
	 * @return array<string,string> Hiệu ứng chuyển động => nhãn.
	 */
	public static function effects(): array {
		return array(
			'none'      => __( 'Không có', 'wp-holidays' ),
			'petals'    => __( 'Cánh hoa rơi', 'wp-holidays' ),
			'snow'      => __( 'Tuyết rơi', 'wp-holidays' ),
			'confetti'  => __( 'Giấy màu', 'wp-holidays' ),
			'lanterns'  => __( 'Đèn lồng bay', 'wp-holidays' ),
			'stars'     => __( 'Sao lấp lánh', 'wp-holidays' ),
			'fireworks' => __( 'Pháo hoa', 'wp-holidays' ),
		);
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public static function presets(): array {
		return array(
			'tet'       => array( 'label' => __( 'Tết (đỏ, vàng, hoa mai)', 'wp-holidays' ), 'bg1' => '#b3121d', 'bg2' => '#6f0a12', 'text' => '#fff6e0', 'accent' => '#ffd166', 'effect' => 'petals', 'motif' => 'blossom' ),
			'new-year'  => array( 'label' => __( 'Năm mới (pháo hoa đêm)', 'wp-holidays' ), 'bg1' => '#14213d', 'bg2' => '#3a0ca3', 'text' => '#ffffff', 'accent' => '#ffd166', 'effect' => 'fireworks', 'motif' => '' ),
			'national'  => array( 'label' => __( 'Quốc gia (cờ đỏ sao vàng)', 'wp-holidays' ), 'bg1' => '#da251d', 'bg2' => '#970f0a', 'text' => '#ffffff', 'accent' => '#ffde00', 'effect' => 'stars', 'motif' => 'star' ),
			'heritage'  => array( 'label' => __( 'Truyền thống (trống đồng)', 'wp-holidays' ), 'bg1' => '#7c4310', 'bg2' => '#3f1f06', 'text' => '#fff4dc', 'accent' => '#f3c969', 'effect' => 'none', 'motif' => 'drum' ),
			'lantern'   => array( 'label' => __( 'Đêm trăng, đèn lồng', 'wp-holidays' ), 'bg1' => '#16204a', 'bg2' => '#4a1d6b', 'text' => '#fff8e7', 'accent' => '#ffb347', 'effect' => 'lanterns', 'motif' => 'moon' ),
			'lotus'     => array( 'label' => __( 'Hoa sen', 'wp-holidays' ), 'bg1' => '#2f6b5b', 'bg2' => '#123d33', 'text' => '#f6fff9', 'accent' => '#f7b6c8', 'effect' => 'petals', 'motif' => 'lotus' ),
			'flower'    => array( 'label' => __( 'Hoa, sắc hồng', 'wp-holidays' ), 'bg1' => '#d63f73', 'bg2' => '#8a1650', 'text' => '#ffffff', 'accent' => '#ffd9e6', 'effect' => 'petals', 'motif' => 'flower' ),
			'education' => array( 'label' => __( 'Tri ân (xanh ngọc)', 'wp-holidays' ), 'bg1' => '#0f766e', 'bg2' => '#0b3f3b', 'text' => '#f4fffc', 'accent' => '#fde68a', 'effect' => 'petals', 'motif' => 'flower' ),
			'family'    => array( 'label' => __( 'Gia đình (ấm áp)', 'wp-holidays' ), 'bg1' => '#c2410c', 'bg2' => '#9f1239', 'text' => '#ffffff', 'accent' => '#ffe4c7', 'effect' => 'none', 'motif' => 'heart' ),
			'kids'      => array( 'label' => __( 'Thiếu nhi (giấy màu)', 'wp-holidays' ), 'bg1' => '#0277bd', 'bg2' => '#5e35b1', 'text' => '#ffffff', 'accent' => '#ffeb3b', 'effect' => 'confetti', 'motif' => '' ),
			'christmas' => array( 'label' => __( 'Giáng sinh', 'wp-holidays' ), 'bg1' => '#14532d', 'bg2' => '#7f1d1d', 'text' => '#ffffff', 'accent' => '#fde68a', 'effect' => 'snow', 'motif' => 'tree' ),
			'memorial'  => array( 'label' => __( 'Tưởng niệm (trang nghiêm)', 'wp-holidays' ), 'bg1' => '#2b2d33', 'bg2' => '#14151a', 'text' => '#f0ece2', 'accent' => '#d9b36c', 'effect' => 'none', 'motif' => 'star' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function preset( string $key ): array {
		$presets = self::presets();
		return $presets[ $key ] ?? $presets['national'];
	}

	/**
	 * Họa tiết SVG trang trí, chỉ sinh từ dữ liệu trong plugin.
	 */
	public static function motif( string $key ): string {
		$body = array(
			'star'    => '<polygon points="' . self::star_points( 5, 92, 35 ) . '"/>',
			'moon'    => '<circle cx="100" cy="100" r="66"/>',
			'heart'   => '<path d="M100 176C40 130 14 98 14 62c0-27 21-46 45-46 17 0 32 9 41 25 9-16 24-25 41-25 24 0 45 19 45 46 0 36-26 68-86 114z"/>',
			'tree'    => '<polygon points="100,12 142,78 118,78 158,136 124,136 170,188 30,188 76,136 42,136 82,78 58,78"/>',
			'flower'  => self::flower( 100, 100, 90 ),
			'blossom' => '<path d="M198 12C150 66 96 96 8 186" fill="none" stroke="currentColor" stroke-width="3"/>' . self::flower( 150, 48, 34 ) . self::flower( 92, 112, 42 ) . self::flower( 36, 148, 26 ),
			'lotus'   => self::lotus(),
			'drum'    => self::drum(),
		);

		if ( ! isset( $body[ $key ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="wph-hero__motif wph-hero__motif--%s" viewBox="0 0 200 200" fill="currentColor" aria-hidden="true" focusable="false">%s</svg>',
			esc_attr( $key ),
			$body[ $key ]
		);
	}

	private static function star_points( int $tips, float $outer, float $inner ): string {
		$points = array();
		for ( $i = 0; $i < $tips * 2; $i++ ) {
			$radius   = 0 === $i % 2 ? $outer : $inner;
			$angle    = M_PI * $i / $tips - M_PI / 2;
			$points[] = round( 100 + $radius * cos( $angle ), 2 ) . ',' . round( 100 + $radius * sin( $angle ), 2 );
		}
		return implode( ' ', $points );
	}

	private static function flower( float $cx, float $cy, float $r ): string {
		$out = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$out .= sprintf(
				'<ellipse cx="%1$s" cy="%2$s" rx="%3$s" ry="%4$s" transform="rotate(%5$d %6$s %7$s)"/>',
				$cx,
				$cy - $r * 0.55,
				round( $r * 0.34, 2 ),
				round( $r * 0.46, 2 ),
				$i * 72,
				$cx,
				$cy
			);
		}
		return $out . sprintf( '<circle cx="%s" cy="%s" r="%s" fill="#fff" opacity=".7"/>', $cx, $cy, round( $r * 0.16, 2 ) );
	}

	private static function lotus(): string {
		$out = '';
		foreach ( array( -80, -54, -27, 0, 27, 54, 80 ) as $angle ) {
			$out .= sprintf( '<path d="M100 170C62 128 72 66 100 22c28 44 38 106 0 148z" opacity=".78" transform="rotate(%d 100 170)"/>', $angle );
		}
		return $out;
	}

	private static function drum(): string {
		$out = '<polygon points="' . self::star_points( 14, 44, 14 ) . '"/>';
		foreach ( array( 54, 66, 80, 94 ) as $i => $r ) {
			$out .= sprintf( '<circle cx="100" cy="100" r="%d" fill="none" stroke="currentColor" stroke-width="%s"/>', $r, 0 === $i % 2 ? '2' : '4' );
		}
		for ( $i = 0; $i < 36; $i++ ) {
			$out .= sprintf( '<rect x="98.5" y="21" width="3" height="10" transform="rotate(%d 100 100)"/>', $i * 10 );
		}
		return $out;
	}
}
