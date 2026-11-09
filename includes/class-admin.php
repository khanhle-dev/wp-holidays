<?php
/**
 * Trang lịch trong năm, cài đặt và tài nguyên quản trị.
 *
 * @package WPHolidays
 */

namespace WPHolidays;

defined( 'ABSPATH' ) || exit;

final class Admin {

	private const OPTION = 'wph_settings';
	private const PAGE   = 'wph-calendar';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_post_wph_restore', array( self::class, 'restore' ) );
	}

	public static function settings(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge(
			array(
				'display'         => 'front_page',
				'min_height'      => 60,
				'container_width' => 1200,
				'scroll_arrow'    => true,
				'scroll_snap'     => false,
			),
			is_array( $saved ) ? $saved : array()
		);
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . PostType::SLUG,
			__( 'Lịch và cài đặt', 'wp-holidays' ),
			__( 'Lịch và cài đặt', 'wp-holidays' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'wph_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
	}

	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$width = (int) ( $input['container_width'] ?? 1200 );

		return array(
			'display'         => in_array( $input['display'] ?? '', array( 'none', 'front_page', 'all' ), true ) ? $input['display'] : 'front_page',
			'min_height'      => max( 30, min( 100, (int) ( $input['min_height'] ?? 60 ) ) ),
			// 0 nghĩa là không giới hạn độ rộng.
			'container_width' => $width <= 0 ? 0 : max( 320, min( 2560, $width ) ),
			'scroll_arrow'    => ! empty( $input['scroll_arrow'] ),
			'scroll_snap'     => ! empty( $input['scroll_snap'] ),
		);
	}

	public static function assets(): void {
		$screen = get_current_screen();

		if ( $screen && PostType::SLUG === $screen->post_type && 'post' === $screen->base ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".wph-color").wpColorPicker();});' );
		}
	}

	public static function restore(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'wp-holidays' ), 403 );
		}
		check_admin_referer( 'wph_restore' );

		wp_safe_redirect( add_query_arg( 'wph_restored', Defaults::restore(), self::url() ) );
		exit;
	}

	private static function url( ?int $year = null ): string {
		$url = admin_url( 'edit.php?post_type=' . PostType::SLUG . '&page=' . self::PAGE );
		return $year ? add_query_arg( 'wph_year', $year, $url ) : $url;
	}

	/**
	 * Các ngày lễ diễn ra trong một năm dương lịch, theo thứ tự thời gian.
	 */
	private static function rows( int $year ): array {
		$rows = array();

		foreach ( Schedule::holidays( array( 'publish', 'draft' ) ) as $post ) {
			$config = PostType::config( $post->ID );
			for ( $y = $year - 1; $y <= $year; $y++ ) {
				$date = Schedule::date_in_year( $config, $y );
				if ( $date && (int) $date->format( 'Y' ) === $year ) {
					$rows[] = compact( 'post', 'config', 'date' );
				}
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['date'] <=> $b['date'];
			}
		);

		return $rows;
	}

	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- Chỉ đọc tham số hiển thị.
		$year     = isset( $_GET['wph_year'] ) ? max( 2000, min( 2100, absint( $_GET['wph_year'] ) ) ) : (int) Schedule::today()->format( 'Y' );
		$restored = isset( $_GET['wph_restored'] ) ? absint( $_GET['wph_restored'] ) : null;
		// phpcs:enable

		$settings = self::settings();
		$active   = Schedule::active();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Lịch và cài đặt', 'wp-holidays' ); ?></h1>

			<?php if ( null !== $restored ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php /* translators: %d: số ngày lễ */ ?>
					<?php echo esc_html( sprintf( __( 'Đã tạo lại %d ngày lễ mặc định.', 'wp-holidays' ), $restored ) ); ?>
				</p></div>
			<?php endif; ?>

			<p>
				<strong><?php esc_html_e( 'Hôm nay:', 'wp-holidays' ); ?></strong>
				<?php echo esc_html( $active ? $active->post_title : __( 'không có hero ngày lễ nào đang hiển thị', 'wp-holidays' ) ); ?>
			</p>

			<h2><?php esc_html_e( 'Cài đặt hiển thị', 'wp-holidays' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'wph_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wph-display"><?php esc_html_e( 'Tự chèn hero', 'wp-holidays' ); ?></label></th>
						<td>
							<select id="wph-display" name="<?php echo esc_attr( self::OPTION ); ?>[display]">
								<option value="front_page" <?php selected( $settings['display'], 'front_page' ); ?>><?php esc_html_e( 'Đầu trang chủ', 'wp-holidays' ); ?></option>
								<option value="all" <?php selected( $settings['display'], 'all' ); ?>><?php esc_html_e( 'Đầu mọi trang', 'wp-holidays' ); ?></option>
								<option value="none" <?php selected( $settings['display'], 'none' ); ?>><?php esc_html_e( 'Tắt', 'wp-holidays' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Chọn Tắt nếu bạn tự đặt hero bằng block "Hero ngày lễ" hoặc shortcode [holiday_hero], để tránh hiện hai lần.', 'wp-holidays' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wph-height"><?php esc_html_e( 'Chiều cao tối thiểu (% màn hình)', 'wp-holidays' ); ?></label></th>
						<td><input id="wph-height" class="small-text" type="number" min="30" max="100" name="<?php echo esc_attr( self::OPTION ); ?>[min_height]" value="<?php echo esc_attr( (string) $settings['min_height'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wph-container"><?php esc_html_e( 'Độ rộng container (px)', 'wp-holidays' ); ?></label></th>
						<td>
							<input id="wph-container" class="small-text" type="number" min="0" max="2560" step="1" name="<?php echo esc_attr( self::OPTION ); ?>[container_width]" value="<?php echo esc_attr( (string) $settings['container_width'] ); ?>">
							<p class="description"><?php esc_html_e( 'Độ rộng tối đa của phần nội dung hero, nên đặt bằng container của theme. Nền vẫn tràn hết chiều ngang. Nhập 0 để không giới hạn.', 'wp-holidays' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mũi tên cuộn xuống', 'wp-holidays' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[scroll_arrow]" value="1" <?php checked( $settings['scroll_arrow'] ); ?>>
								<?php esc_html_e( 'Hiện mũi tên ở cuối hero, bấm vào để cuộn đến phần tiếp theo', 'wp-holidays' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cuộn một lần qua hero', 'wp-holidays' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[scroll_snap]" value="1" <?php checked( $settings['scroll_snap'] ); ?>>
								<?php esc_html_e( 'Khi đang ở hero, cuộn chuột hoặc vuốt xuống một lần sẽ chuyển thẳng đến phần tiếp theo', 'wp-holidays' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Không áp dụng cho người dùng đã bật giảm chuyển động trên thiết bị, hoặc khi nội dung hero dài hơn một màn hình.', 'wp-holidays' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2>
				<?php /* translators: %d: năm */ ?>
				<?php echo esc_html( sprintf( __( 'Lịch ngày lễ năm %d', 'wp-holidays' ), $year ) ); ?>
				<a class="page-title-action" href="<?php echo esc_url( self::url( $year - 1 ) ); ?>">&larr; <?php echo esc_html( (string) ( $year - 1 ) ); ?></a>
				<a class="page-title-action" href="<?php echo esc_url( self::url( $year + 1 ) ); ?>"><?php echo esc_html( (string) ( $year + 1 ) ); ?> &rarr;</a>
			</h2>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Dương lịch', 'wp-holidays' ); ?></th>
						<th><?php esc_html_e( 'Thứ', 'wp-holidays' ); ?></th>
						<th><?php esc_html_e( 'Ngày lễ', 'wp-holidays' ); ?></th>
						<th><?php esc_html_e( 'Theo lịch', 'wp-holidays' ); ?></th>
						<th><?php esc_html_e( 'Hiển thị từ - đến', 'wp-holidays' ); ?></th>
						<th><?php esc_html_e( 'Trạng thái', 'wp-holidays' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( self::rows( $year ) as $row ) :
						list( $start, $end ) = Schedule::window( $row['config'], $row['date'] );
						?>
						<tr>
							<td><?php echo esc_html( $row['date']->format( 'd/m/Y' ) ); ?></td>
							<td><?php echo esc_html( wp_date( 'l', $row['date']->getTimestamp(), wp_timezone() ) ); ?></td>
							<td><a href="<?php echo esc_url( (string) get_edit_post_link( $row['post']->ID ) ); ?>"><strong><?php echo esc_html( $row['post']->post_title ); ?></strong></a></td>
							<td><?php echo esc_html( PostType::date_label( $row['config'] ) ); ?></td>
							<td><?php echo esc_html( $start->format( 'd/m' ) . ' - ' . $end->format( 'd/m' ) ); ?></td>
							<td><?php echo esc_html( 'publish' === $row['post']->post_status ? __( 'Bật', 'wp-holidays' ) : __( 'Tắt', 'wp-holidays' ) ); ?></td>
							<td><a href="<?php echo esc_url( PostType::preview_url( $row['post']->ID ) ); ?>" target="_blank"><?php esc_html_e( 'Xem trước', 'wp-holidays' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em">
				<input type="hidden" name="action" value="wph_restore">
				<?php wp_nonce_field( 'wph_restore' ); ?>
				<?php submit_button( __( 'Tạo lại các ngày lễ mặc định đã xóa', 'wp-holidays' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
