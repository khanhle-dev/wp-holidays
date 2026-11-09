<?php
/**
 * Plugin Name:       WP Holidays Hero
 * Description:       Tự động hiển thị hero section theo ngày lễ trong năm, có sẵn các ngày lễ Việt Nam (cả dương lịch và âm lịch).
 * Version:           1.2.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-holidays
 *
 * @package WPHolidays
 */

namespace WPHolidays;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.2.0';
const FILE    = __FILE__;

require_once __DIR__ . '/includes/class-lunar-calendar.php';
require_once __DIR__ . '/includes/class-themes.php';
require_once __DIR__ . '/includes/class-post-type.php';
require_once __DIR__ . '/includes/class-schedule.php';
require_once __DIR__ . '/includes/class-defaults.php';
require_once __DIR__ . '/includes/class-renderer.php';
require_once __DIR__ . '/includes/class-admin.php';

register_activation_hook( __FILE__, array( Defaults::class, 'activate' ) );

PostType::init();
Schedule::init();
Defaults::init();
Renderer::init();
Admin::init();
