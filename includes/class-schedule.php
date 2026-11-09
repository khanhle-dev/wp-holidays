<?php
/**
 * Tính ngày diễn ra và chọn ngày lễ đang hiển thị.
 *
 * @package WPHolidays
 */

namespace WPHolidays;

use DateTimeImmutable;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Schedule {

	private const CACHE_KEY = 'wph_active';

	public static function init(): void {
		add_action( 'save_post_' . PostType::SLUG, array( self::class, 'flush' ) );
		add_action( 'deleted_post', array( self::class, 'flush' ) );
	}

	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Hôm nay theo múi giờ của site, lúc 00:00.
	 */
	public static function today(): DateTimeImmutable {
		return apply_filters( 'wph_today', new DateTimeImmutable( 'today', wp_timezone() ) );
	}

	/**
	 * Ngày diễn ra trong một năm (năm âm lịch nếu là lễ âm lịch).
	 */
	public static function date_in_year( array $config, int $year ): ?DateTimeImmutable {
		$day   = (int) $config['day'];
		$month = (int) $config['month'];

		if ( 'lunar' === $config['calendar'] ) {
			list( $y, $m, $d ) = LunarCalendar::to_solar( $day, $month, $year );
		} elseif ( checkdate( $month, $day, $year ) ) {
			list( $y, $m, $d ) = array( $year, $month, $day );
		} else {
			return null; // 29/2 của năm không nhuận.
		}

		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->setDate( $y, $m, $d )->setTime( 0, 0 );
	}

	/**
	 * Lần diễn ra có khung hiển thị chứa ngày đã cho.
	 */
	public static function current_occurrence( array $config, DateTimeImmutable $today ): ?array {
		$key  = $today->format( 'Y-m-d' );
		$year = (int) $today->format( 'Y' );

		for ( $y = $year - 1; $y <= $year + 1; $y++ ) {
			$date = self::date_in_year( $config, $y );
			if ( ! $date ) {
				continue;
			}
			list( $start, $end ) = self::window( $config, $date );
			if ( $start->format( 'Y-m-d' ) <= $key && $key <= $end->format( 'Y-m-d' ) ) {
				return array( 'date' => $date, 'year' => $y );
			}
		}

		return null;
	}

	/**
	 * Lần diễn ra gần nhất tính từ ngày đã cho (kể cả ngày đó).
	 */
	public static function next_occurrence( array $config, DateTimeImmutable $today ): ?array {
		$key  = $today->format( 'Y-m-d' );
		$year = (int) $today->format( 'Y' );

		// Lễ 29/2 có thể cách tới 8 năm (qua năm chia hết cho 100).
		for ( $y = $year - 1; $y <= $year + 8; $y++ ) {
			$date = self::date_in_year( $config, $y );
			if ( $date && $date->format( 'Y-m-d' ) >= $key ) {
				return array( 'date' => $date, 'year' => $y );
			}
		}

		return null;
	}

	/**
	 * @return DateTimeImmutable[] Ngày bắt đầu và kết thúc hiển thị.
	 */
	public static function window( array $config, DateTimeImmutable $date ): array {
		return array(
			$date->modify( '-' . (int) $config['before'] . ' days' ),
			$date->modify( '+' . (int) $config['after'] . ' days' ),
		);
	}

	/**
	 * Năm âm lịch của một lần diễn ra, dùng cho {lunar_year}.
	 */
	public static function lunar_year( array $config, array $occurrence ): int {
		if ( 'lunar' === $config['calendar'] ) {
			return $occurrence['year'];
		}

		$year = (int) $occurrence['date']->format( 'Y' );
		$tet  = LunarCalendar::to_solar( 1, 1, $year );

		return $occurrence['date']->format( 'Y-m-d' ) < sprintf( '%04d-%02d-%02d', $tet[0], $tet[1], $tet[2] ) ? $year - 1 : $year;
	}

	/**
	 * Các ngày lễ đang bật.
	 *
	 * @return WP_Post[]
	 */
	public static function holidays( array $status = array( 'publish' ) ): array {
		return get_posts(
			array(
				'post_type'        => PostType::SLUG,
				'post_status'      => $status,
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
	}

	public static function by_slug( string $slug ): ?WP_Post {
		$found = get_posts(
			array(
				'post_type'        => PostType::SLUG,
				'post_status'      => 'publish',
				'name'             => sanitize_title( $slug ),
				'numberposts'      => 1,
				'suppress_filters' => true,
			)
		);
		return $found[0] ?? null;
	}

	/**
	 * Ngày lễ cần hiển thị hôm nay. Khi nhiều lễ trùng khung hiển thị:
	 * ưu tiên cao hơn thắng, sau đó đến lễ gần ngày diễn ra hơn.
	 */
	public static function active(): ?WP_Post {
		$today = self::today();
		$key   = $today->format( 'Y-m-d' );

		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && $cached['date'] === $key ) {
			$post = $cached['id'] ? get_post( $cached['id'] ) : null;
			return $post && 'publish' === $post->post_status ? $post : null;
		}

		$best      = null;
		$best_rank = null;

		foreach ( self::holidays() as $post ) {
			$config     = PostType::config( $post->ID );
			$occurrence = self::current_occurrence( $config, $today );
			if ( ! $occurrence ) {
				continue;
			}
			$rank = array( -(int) $config['priority'], (int) $today->diff( $occurrence['date'] )->days, $post->ID );
			if ( null === $best_rank || $rank < $best_rank ) {
				$best      = $post;
				$best_rank = $rank;
			}
		}

		set_transient( self::CACHE_KEY, array( 'date' => $key, 'id' => $best ? $best->ID : 0 ), DAY_IN_SECONDS );

		return $best;
	}

	/**
	 * Ngày lễ đang bật sẽ đến gần nhất, dùng cho bản xem trước trong trình soạn thảo.
	 */
	public static function upcoming(): ?WP_Post {
		$today = self::today();
		$best  = null;
		$first = null;

		foreach ( self::holidays() as $post ) {
			$next = self::next_occurrence( PostType::config( $post->ID ), $today );
			if ( $next && ( null === $first || $next['date'] < $first ) ) {
				$best  = $post;
				$first = $next['date'];
			}
		}

		return $best;
	}
}
