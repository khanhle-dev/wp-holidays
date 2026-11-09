( function () {
	var LOCK_MS = 900;
	var LOCK_MAX_MS = 1600;
	var lockUntil = 0;
	var lockLimit = 0;

	function reducedMotion() {
		return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	// Phần bị thanh admin và header dính che ở mép trên màn hình.
	function topOffset( hero ) {
		var bar = document.getElementById( 'wpadminbar' );
		var barHeight = bar && getComputedStyle( bar ).position === 'fixed' ? bar.offsetHeight : 0;
		var headerHeight = 0;

		document.querySelectorAll( 'header, .site-header' ).forEach( function ( header ) {
			var position = getComputedStyle( header ).position;
			// Header sticky nằm sau hero chỉ dính khi cuộn qua nó nên không che phần tiếp theo.
			var precedesHero = header.compareDocumentPosition( hero ) & Node.DOCUMENT_POSITION_FOLLOWING;

			if ( ! header.contains( hero ) && ( position === 'fixed' || ( position === 'sticky' && precedesHero ) ) ) {
				headerHeight = Math.max( headerHeight, header.offsetHeight );
			}
		} );

		return barHeight + headerHeight;
	}

	function scrollPast( hero ) {
		window.scrollTo( {
			top: hero.getBoundingClientRect().bottom + window.scrollY - topOffset( hero ),
			behavior: reducedMotion() ? 'auto' : 'smooth',
		} );
	}

	function isLocked() {
		var now = Date.now();
		return now < lockUntil && now < lockLimit;
	}

	// Nuốt các sự kiện wheel còn lại (quán tính trackpad) để trang không trôi quá phần tiếp theo.
	function holdWheel( event ) {
		if ( ! isLocked() ) {
			window.removeEventListener( 'wheel', holdWheel );
			return;
		}
		event.preventDefault();
		lockUntil = Math.max( lockUntil, Date.now() + 150 );
	}

	function lock() {
		var now = Date.now();
		lockUntil = now + LOCK_MS;
		lockLimit = now + LOCK_MAX_MS;
		window.addEventListener( 'wheel', holdWheel, { passive: false } );
	}

	// Chỉ chuyển khi hero đang chiếm màn hình và nội dung hero không dài quá một màn hình.
	function canAdvance( hero ) {
		var rect = hero.getBoundingClientRect();

		return ! reducedMotion() &&
			rect.height <= window.innerHeight + 2 &&
			rect.top <= window.innerHeight * 0.25 &&
			rect.bottom - topOffset( hero ) > 2;
	}

	function bindSnap( hero ) {
		var touch = null;

		hero.addEventListener( 'wheel', function ( event ) {
			if ( isLocked() || event.ctrlKey || event.deltaY <= 0 || Math.abs( event.deltaX ) > event.deltaY || ! canAdvance( hero ) ) {
				return;
			}
			event.preventDefault();
			lock();
			scrollPast( hero );
		}, { passive: false } );

		hero.addEventListener( 'touchstart', function ( event ) {
			touch = event.touches.length === 1 ? { x: event.touches[ 0 ].clientX, y: event.touches[ 0 ].clientY, done: false } : null;
		}, { passive: true } );

		hero.addEventListener( 'touchmove', function ( event ) {
			if ( ! touch ) {
				return;
			}
			if ( touch.done ) {
				if ( event.cancelable ) {
					event.preventDefault();
				}
				return;
			}

			var dy = touch.y - event.touches[ 0 ].clientY;
			var dx = touch.x - event.touches[ 0 ].clientX;

			// Chỉ xử lý vuốt lên. Vuốt xuống hoặc ngang giữ nguyên hành vi của trình duyệt.
			if ( dy > 0 && dy > Math.abs( dx ) && event.cancelable && canAdvance( hero ) ) {
				event.preventDefault();
				touch.done = true;
				scrollPast( hero );
			} else {
				touch = null;
			}
		}, { passive: false } );
	}

	document.querySelectorAll( '.wph-hero--snap' ).forEach( bindSnap );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest && event.target.closest( '.wph-hero__scroll' );

		if ( button ) {
			scrollPast( button.closest( '.wph-hero' ) );
		}
	} );
} )();
