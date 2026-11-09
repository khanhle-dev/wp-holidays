<?php
/**
 * Danh sách ngày lễ Việt Nam tạo sẵn.
 *
 * @package WPHolidays
 */

namespace WPHolidays;

defined( 'ABSPATH' ) || exit;

final class Defaults {

	private const SEEDED_OPTION = 'wph_seeded';

	public static function init(): void {
		// Bao phủ cả trường hợp kích hoạt toàn mạng (multisite) và bản cập nhật có thêm lễ mới.
		add_action( 'admin_init', array( self::class, 'seed' ) );
	}

	public static function activate(): void {
		PostType::register();
		self::seed();
	}

	/**
	 * Tạo các ngày lễ chưa từng được tạo. Lễ người dùng đã xóa sẽ không tự quay lại.
	 */
	public static function seed(): void {
		$seeded  = (array) get_option( self::SEEDED_OPTION, array() );
		$missing = array_diff_key( self::holidays(), array_flip( $seeded ) );

		if ( ! $missing ) {
			return;
		}

		foreach ( $missing as $slug => $holiday ) {
			self::insert( $slug, $holiday );
			$seeded[] = $slug;
		}

		update_option( self::SEEDED_OPTION, array_values( array_unique( $seeded ) ), false );
	}

	/**
	 * Tạo lại những ngày lễ mặc định hiện không còn trong danh sách.
	 *
	 * @return int Số ngày lễ đã tạo lại.
	 */
	public static function restore(): int {
		$count = 0;
		foreach ( self::holidays() as $slug => $holiday ) {
			$count += self::insert( $slug, $holiday ) ? 1 : 0;
		}
		return $count;
	}

	private static function insert( string $slug, array $holiday ): bool {
		$exists = get_posts(
			array(
				'post_type'        => PostType::SLUG,
				'post_status'      => 'any',
				'name'             => $slug,
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		if ( $exists ) {
			return false;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => PostType::SLUG,
				'post_status' => $holiday['enabled'] ? 'publish' : 'draft',
				'post_title'  => $holiday['title'],
				'post_name'   => $slug,
				'meta_input'  => array( PostType::META => PostType::sanitize( $holiday ) ),
			)
		);

		return ! is_wp_error( $post_id ) && $post_id > 0;
	}

	/**
	 * Tạo một dòng dữ liệu ngày lễ.
	 *
	 * @param string $title    Tên ngày lễ.
	 * @param string $calendar solar hoặc lunar.
	 * @param int[]  $date     [ngày, tháng].
	 * @param int[]  $window   [số ngày hiện trước, số ngày hiện sau, độ ưu tiên].
	 * @param string $theme    Mã giao diện.
	 * @param string $eyebrow  Dòng nhỏ phía trên.
	 * @param string $heading  Tiêu đề lớn.
	 * @param string $sub      Mô tả.
	 * @param bool   $enabled  Có bật sẵn không.
	 */
	private static function row( string $title, string $calendar, array $date, array $window, string $theme, string $eyebrow, string $heading, string $sub, bool $enabled = true ): array {
		return array(
			'title'      => $title,
			'calendar'   => $calendar,
			'day'        => $date[0],
			'month'      => $date[1],
			'before'     => $window[0],
			'after'      => $window[1],
			'priority'   => $window[2],
			'theme'      => $theme,
			'eyebrow'    => $eyebrow,
			'heading'    => $heading,
			'subheading' => $sub,
			'enabled'    => $enabled,
		);
	}

	/**
	 * Ngày nghỉ lễ chính thức ưu tiên 20. Lễ mang tính ngành, kỷ niệm chính trị
	 * và tưởng niệm được tạo ở trạng thái nháp để site chủ động bật khi phù hợp.
	 *
	 * @return array<string,array>
	 */
	public static function holidays(): array {
		$holidays = array(
			// Dương lịch.
			'tet-duong-lich'        => self::row( 'Tết Dương lịch', 'solar', array( 1, 1 ), array( 3, 1, 20 ), 'new-year', 'Tết Dương lịch', 'Chúc mừng năm mới {year}', 'Chúc bạn một năm mới khỏe mạnh, bình an và tràn đầy niềm vui.' ),
			'thanh-lap-dang'        => self::row( 'Ngày thành lập Đảng Cộng sản Việt Nam', 'solar', array( 3, 2 ), array( 1, 0, 5 ), 'national', '3/2', 'Mừng Đảng, mừng Xuân', 'Kỷ niệm ngày thành lập Đảng Cộng sản Việt Nam.', false ),
			'le-tinh-nhan'          => self::row( 'Lễ Tình nhân', 'solar', array( 14, 2 ), array( 2, 0, 10 ), 'flower', '14/2', 'Valentine ngọt ngào', 'Gửi yêu thương đến người quan trọng nhất của bạn.' ),
			'thay-thuoc-viet-nam'   => self::row( 'Ngày Thầy thuốc Việt Nam', 'solar', array( 27, 2 ), array( 1, 0, 5 ), 'education', '27/2', 'Tri ân Thầy thuốc Việt Nam', 'Lương y như từ mẫu. Cảm ơn những người ngày đêm giữ gìn sức khỏe cộng đồng.', false ),
			'quoc-te-phu-nu'        => self::row( 'Ngày Quốc tế Phụ nữ', 'solar', array( 8, 3 ), array( 2, 0, 10 ), 'flower', 'Quốc tế Phụ nữ 8/3', 'Tôn vinh một nửa thế giới', 'Chúc những người phụ nữ tuyệt vời luôn rạng rỡ, hạnh phúc và được yêu thương.' ),
			'thanh-lap-doan'        => self::row( 'Ngày thành lập Đoàn TNCS Hồ Chí Minh', 'solar', array( 26, 3 ), array( 1, 0, 5 ), 'national', '26/3', 'Tuổi trẻ xung kích, sáng tạo', 'Kỷ niệm ngày thành lập Đoàn Thanh niên Cộng sản Hồ Chí Minh.', false ),
			'giai-phong-mien-nam'   => self::row( 'Ngày Giải phóng miền Nam', 'solar', array( 30, 4 ), array( 2, 0, 20 ), 'national', '30/4', 'Non sông liền một dải', 'Kỷ niệm ngày Giải phóng miền Nam, thống nhất đất nước.' ),
			'quoc-te-lao-dong'      => self::row( 'Ngày Quốc tế Lao động', 'solar', array( 1, 5 ), array( 0, 1, 20 ), 'national', 'Quốc tế Lao động 1/5', 'Tự hào người lao động', 'Trân trọng những đóng góp của mỗi người cho cuộc sống hôm nay.' ),
			'chien-thang-dien-bien' => self::row( 'Ngày Chiến thắng Điện Biên Phủ', 'solar', array( 7, 5 ), array( 0, 0, 5 ), 'national', '7/5', 'Lừng lẫy Điện Biên', 'Kỷ niệm Chiến thắng Điện Biên Phủ, chấn động địa cầu.', false ),
			'sinh-nhat-bac'         => self::row( 'Ngày sinh Chủ tịch Hồ Chí Minh', 'solar', array( 19, 5 ), array( 0, 0, 5 ), 'national', '19/5', 'Kính nhớ Bác Hồ', 'Kỷ niệm ngày sinh Chủ tịch Hồ Chí Minh.', false ),
			'quoc-te-thieu-nhi'     => self::row( 'Ngày Quốc tế Thiếu nhi', 'solar', array( 1, 6 ), array( 1, 0, 10 ), 'kids', 'Quốc tế Thiếu nhi 1/6', 'Vui hết mình cùng các bé', 'Chúc các em luôn hồn nhiên, khỏe mạnh và ngập tràn tiếng cười.' ),
			'bao-chi-cach-mang'     => self::row( 'Ngày Báo chí Cách mạng Việt Nam', 'solar', array( 21, 6 ), array( 0, 0, 5 ), 'education', '21/6', 'Tri ân người làm báo', 'Chúc mừng Ngày Báo chí Cách mạng Việt Nam.', false ),
			'gia-dinh-viet-nam'     => self::row( 'Ngày Gia đình Việt Nam', 'solar', array( 28, 6 ), array( 1, 0, 10 ), 'family', '28/6', 'Gia đình là tất cả', 'Dành thời gian cho những người thân yêu nhất.' ),
			'thuong-binh-liet-si'   => self::row( 'Ngày Thương binh Liệt sĩ', 'solar', array( 27, 7 ), array( 0, 0, 25 ), 'memorial', '27/7', 'Đời đời nhớ ơn', 'Tri ân các anh hùng liệt sĩ và thương binh đã hy sinh vì Tổ quốc.', false ),
			'cach-mang-thang-tam'   => self::row( 'Ngày Cách mạng Tháng Tám', 'solar', array( 19, 8 ), array( 0, 0, 5 ), 'national', '19/8', 'Mùa thu lịch sử', 'Kỷ niệm Cách mạng Tháng Tám thành công.', false ),
			'quoc-khanh'            => self::row( 'Quốc khánh', 'solar', array( 2, 9 ), array( 2, 1, 20 ), 'national', 'Quốc khánh 2/9', 'Mừng Tết Độc lập', 'Tự hào Việt Nam. Chúc mừng ngày Quốc khánh nước Cộng hòa Xã hội Chủ nghĩa Việt Nam.' ),
			'phu-nu-viet-nam'       => self::row( 'Ngày Phụ nữ Việt Nam', 'solar', array( 20, 10 ), array( 2, 0, 10 ), 'flower', 'Phụ nữ Việt Nam 20/10', 'Gửi trọn yêu thương', 'Chúc phụ nữ Việt Nam luôn xinh đẹp, tự tin và hạnh phúc.' ),
			'nha-giao-viet-nam'     => self::row( 'Ngày Nhà giáo Việt Nam', 'solar', array( 20, 11 ), array( 3, 0, 10 ), 'education', 'Nhà giáo Việt Nam 20/11', 'Tri ân thầy cô', 'Một chữ cũng là thầy, nửa chữ cũng là thầy. Kính chúc thầy cô luôn mạnh khỏe.' ),
			'van-hoa-viet-nam'      => self::row( 'Ngày Văn hóa Việt Nam', 'solar', array( 24, 11 ), array( 1, 0, 20 ), 'heritage', '24/11', 'Ngày Văn hóa Việt Nam', 'Gìn giữ và lan tỏa những giá trị văn hóa tốt đẹp của dân tộc.' ),
			'quan-doi-nhan-dan'     => self::row( 'Ngày thành lập Quân đội Nhân dân Việt Nam', 'solar', array( 22, 12 ), array( 0, 0, 15 ), 'national', '22/12', 'Bộ đội Cụ Hồ', 'Kỷ niệm ngày thành lập Quân đội Nhân dân Việt Nam.', false ),
			'giang-sinh'            => self::row( 'Giáng sinh', 'solar', array( 25, 12 ), array( 3, 1, 10 ), 'christmas', 'Merry Christmas', 'Giáng sinh an lành', 'Chúc bạn và gia đình một mùa Giáng sinh ấm áp, an vui.' ),

			// Âm lịch.
			'tet-nguyen-dan'        => self::row( 'Tết Nguyên Đán', 'lunar', array( 1, 1 ), array( 6, 6, 20 ), 'tet', 'Cung chúc tân xuân', 'Xuân {lunar_year} {year}', 'An khang thịnh vượng, vạn sự như ý.' ),
			'tet-nguyen-tieu'       => self::row( 'Tết Nguyên Tiêu', 'lunar', array( 15, 1 ), array( 1, 0, 10 ), 'lantern', 'Rằm tháng Giêng', 'Tết Nguyên Tiêu', 'Lễ quanh năm không bằng rằm tháng Giêng. Chúc bạn một năm bình an, sung túc.' ),
			'tet-han-thuc'          => self::row( 'Tết Hàn thực', 'lunar', array( 3, 3 ), array( 0, 0, 5 ), 'heritage', 'Mùng 3 tháng 3 âm lịch', 'Tết Hàn thực', 'Bánh trôi, bánh chay và nét đẹp nhớ về cội nguồn.', false ),
			'gio-to-hung-vuong'     => self::row( 'Giỗ Tổ Hùng Vương', 'lunar', array( 10, 3 ), array( 2, 0, 20 ), 'heritage', 'Mùng 10 tháng 3 âm lịch', 'Giỗ Tổ Hùng Vương', 'Dù ai đi ngược về xuôi, nhớ ngày Giỗ Tổ mùng Mười tháng Ba.' ),
			'phat-dan'              => self::row( 'Lễ Phật Đản', 'lunar', array( 15, 4 ), array( 1, 0, 10 ), 'lotus', 'Rằm tháng Tư', 'Mừng ngày Phật Đản', 'Nguyện cầu an lạc và từ bi lan tỏa khắp muôn nơi.' ),
			'tet-doan-ngo'          => self::row( 'Tết Đoan Ngọ', 'lunar', array( 5, 5 ), array( 1, 0, 10 ), 'heritage', 'Mùng 5 tháng 5 âm lịch', 'Tết Đoan Ngọ', 'Cơm rượu, trái cây đầu mùa và lời chúc mạnh khỏe.' ),
			'vu-lan'                => self::row( 'Lễ Vu Lan', 'lunar', array( 15, 7 ), array( 2, 0, 10 ), 'lotus', 'Rằm tháng Bảy', 'Mùa Vu Lan báo hiếu', 'Công cha như núi Thái Sơn, nghĩa mẹ như nước trong nguồn chảy ra.' ),
			'trung-thu'             => self::row( 'Tết Trung Thu', 'lunar', array( 15, 8 ), array( 5, 0, 10 ), 'lantern', 'Rằm tháng Tám', 'Trung Thu đoàn viên', 'Trăng tròn, bánh ngọt và những khoảnh khắc sum vầy bên gia đình.' ),
			'ong-cong-ong-tao'      => self::row( 'Ông Công Ông Táo', 'lunar', array( 23, 12 ), array( 1, 0, 30 ), 'heritage', '23 tháng Chạp', 'Tiễn Ông Công Ông Táo về trời', 'Chúc gia đình bạn một năm mới đủ đầy, ấm no.' ),
		);

		return apply_filters( 'wph_default_holidays', $holidays );
	}
}
