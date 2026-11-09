<?php
/**
 * Đổi âm lịch Việt Nam sang dương lịch (múi giờ UTC+7).
 *
 * Dựa trên thuật toán thiên văn của Hồ Ngọc Đức. Âm lịch Việt Nam phải tính
 * theo UTC+7 nên có năm lệch ngày so với lịch Trung Quốc (ví dụ Tết 2007).
 *
 * @package WPHolidays
 */

namespace WPHolidays;

defined( 'ABSPATH' ) || exit;

final class LunarCalendar {

	private const TIMEZONE = 7;
	private const SYNODIC  = 29.530588853;
	private const EPOCH    = 2415021.076998695;

	private const CAN = array( 'Giáp', 'Ất', 'Bính', 'Đinh', 'Mậu', 'Kỷ', 'Canh', 'Tân', 'Nhâm', 'Quý' );
	private const CHI = array( 'Tý', 'Sửu', 'Dần', 'Mão', 'Thìn', 'Tỵ', 'Ngọ', 'Mùi', 'Thân', 'Dậu', 'Tuất', 'Hợi' );

	/**
	 * Đổi một ngày âm lịch (tháng thường, không nhuận) sang dương lịch.
	 *
	 * Ngày 30 của tháng thiếu được tính là ngày cuối tháng (29).
	 *
	 * @return int[] Mảng [năm, tháng, ngày] dương lịch.
	 */
	public static function to_solar( int $day, int $month, int $year ): array {
		$a11 = self::lunar_month_11( $month < 11 ? $year - 1 : $year );
		$b11 = self::lunar_month_11( $month < 11 ? $year : $year + 1 );
		$k   = (int) floor( 0.5 + ( $a11 - self::EPOCH ) / self::SYNODIC );

		$offset = $month - 11;
		if ( $offset < 0 ) {
			$offset += 12;
		}

		// Năm nhuận: các tháng từ sau tháng nhuận bị lùi thêm một kỳ trăng.
		if ( $b11 - $a11 > 365 && $offset >= self::leap_month_offset( $a11 ) ) {
			++$offset;
		}

		$month_start = self::new_moon_day( $k + $offset );
		$month_end   = self::new_moon_day( $k + $offset + 1 );

		return self::jd_to_date( $month_start + min( $day, $month_end - $month_start ) - 1 );
	}

	/**
	 * Tên can chi của một năm âm lịch, ví dụ 2026 là "Bính Ngọ".
	 */
	public static function can_chi( int $year ): string {
		return self::CAN[ ( $year + 6 ) % 10 ] . ' ' . self::CHI[ ( $year + 8 ) % 12 ];
	}

	private static function jd_from_date( int $day, int $month, int $year ): int {
		$a = intdiv( 14 - $month, 12 );
		$y = $year + 4800 - $a;
		$m = $month + 12 * $a - 3;

		return $day + intdiv( 153 * $m + 2, 5 ) + 365 * $y + intdiv( $y, 4 ) - intdiv( $y, 100 ) + intdiv( $y, 400 ) - 32045;
	}

	/**
	 * @return int[] Mảng [năm, tháng, ngày].
	 */
	private static function jd_to_date( int $jd ): array {
		$a = $jd + 32044;
		$b = intdiv( 4 * $a + 3, 146097 );
		$c = $a - intdiv( $b * 146097, 4 );
		$d = intdiv( 4 * $c + 3, 1461 );
		$e = $c - intdiv( 1461 * $d, 4 );
		$m = intdiv( 5 * $e + 2, 153 );

		return array(
			$b * 100 + $d - 4800 + intdiv( $m, 10 ),
			$m + 3 - 12 * intdiv( $m, 10 ),
			$e - intdiv( 153 * $m + 2, 5 ) + 1,
		);
	}

	private static function new_moon( int $k ): float {
		$t  = $k / 1236.85;
		$t2 = $t * $t;
		$t3 = $t2 * $t;
		$dr = M_PI / 180;

		$jd1  = 2415020.75933 + 29.53058868 * $k + 0.0001178 * $t2 - 0.000000155 * $t3;
		$jd1 += 0.00033 * sin( ( 166.56 + 132.87 * $t - 0.009173 * $t2 ) * $dr );

		$m   = 359.2242 + 29.10535608 * $k - 0.0000333 * $t2 - 0.00000347 * $t3;
		$mpr = 306.0253 + 385.81691806 * $k + 0.0107306 * $t2 + 0.00001236 * $t3;
		$f   = 21.2964 + 390.67050646 * $k - 0.0016528 * $t2 - 0.00000239 * $t3;

		$c1  = ( 0.1734 - 0.000393 * $t ) * sin( $m * $dr ) + 0.0021 * sin( 2 * $dr * $m );
		$c1 -= 0.4068 * sin( $mpr * $dr ) + 0.0161 * sin( $dr * 2 * $mpr );
		$c1 -= 0.0004 * sin( $dr * 3 * $mpr );
		$c1 += 0.0104 * sin( $dr * 2 * $f ) - 0.0051 * sin( $dr * ( $m + $mpr ) );
		$c1 -= 0.0074 * sin( $dr * ( $m - $mpr ) ) + 0.0004 * sin( $dr * ( 2 * $f + $m ) );
		$c1 -= 0.0004 * sin( $dr * ( 2 * $f - $m ) ) - 0.0006 * sin( $dr * ( 2 * $f + $mpr ) );
		$c1 += 0.0010 * sin( $dr * ( 2 * $f - $mpr ) ) + 0.0005 * sin( $dr * ( 2 * $mpr + $m ) );

		$delta = $t < -11
			? 0.001 + 0.000839 * $t + 0.0002261 * $t2 - 0.00000845 * $t3 - 0.000000081 * $t * $t3
			: -0.000278 + 0.000265 * $t + 0.000262 * $t2;

		return $jd1 + $c1 - $delta;
	}

	private static function new_moon_day( int $k ): int {
		return (int) floor( self::new_moon( $k ) + 0.5 + self::TIMEZONE / 24 );
	}

	/**
	 * Cung hoàng đạo (0-11) của Mặt Trời vào đầu ngày Julius.
	 */
	private static function sun_sector( int $jd ): int {
		$t  = ( $jd - 0.5 - self::TIMEZONE / 24 - 2451545.0 ) / 36525;
		$t2 = $t * $t;
		$dr = M_PI / 180;

		$m  = 357.52910 + 35999.05030 * $t - 0.0001559 * $t2 - 0.00000048 * $t * $t2;
		$l0 = 280.46645 + 36000.76983 * $t + 0.0003032 * $t2;

		$dl  = ( 1.914600 - 0.004817 * $t - 0.000014 * $t2 ) * sin( $dr * $m );
		$dl += ( 0.019993 - 0.000101 * $t ) * sin( $dr * 2 * $m ) + 0.000290 * sin( $dr * 3 * $m );

		$omega = 125.04 - 1934.136 * $t;
		$l     = ( $l0 + $dl - 0.00569 - 0.00478 * sin( $omega * $dr ) ) * $dr;
		$l    -= M_PI * 2 * floor( $l / ( M_PI * 2 ) );

		return (int) floor( $l / M_PI * 6 );
	}

	/**
	 * Ngày Julius bắt đầu tháng 11 âm lịch (tháng chứa Đông chí) của một năm.
	 */
	private static function lunar_month_11( int $year ): int {
		$off = self::jd_from_date( 31, 12, $year ) - 2415021;
		$k   = (int) floor( $off / self::SYNODIC );
		$nm  = self::new_moon_day( $k );

		return self::sun_sector( $nm ) >= 9 ? self::new_moon_day( $k - 1 ) : $nm;
	}

	/**
	 * Vị trí tháng nhuận, tính từ tháng 11 âm lịch.
	 */
	private static function leap_month_offset( int $a11 ): int {
		$k   = (int) floor( ( $a11 - self::EPOCH ) / self::SYNODIC + 0.5 );
		$i   = 1;
		$arc = self::sun_sector( self::new_moon_day( $k + $i ) );

		do {
			$last = $arc;
			++$i;
			$arc = self::sun_sector( self::new_moon_day( $k + $i ) );
		} while ( $arc !== $last && $i < 14 );

		return $i - 1;
	}
}
