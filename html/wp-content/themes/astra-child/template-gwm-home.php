<?php
/**
 * Template Name: GWM Home (Dark)
 *
 * Self-contained, dark, Tesla-inspired GWM landing page for HAGBES.
 * Renders its own header/footer (does not use the Astra header) so the
 * page is fully custom while the rest of the site keeps using Astra.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$u = '/wp-content/uploads'; // Uploads base (site runs at root).

// Brand category cards.
$brands = array(
	array(
		'name'  => 'HAVAL',
		'tag'   => 'Intelligent SUVs for every journey',
		'img'   => $u . '/2026/07/GWM-HAVAL-Jolion-Pro.jpg',
		'power' => array( 'ICE', 'HEV', 'PHEV' ),
		'link'  => '/haval-h6/',
	),
	array(
		'name'  => 'TANK',
		'tag'   => 'Luxury off-road, redefined',
		'img'   => $u . '/2026/07/tank-500-a-1600x808.jpg',
		'power' => array( 'ICE', 'HEV', 'PHEV' ),
		'link'  => '/tank-500/',
	),
	array(
		'name'  => 'POER',
		'tag'   => 'Power. Strength. Capability.',
		'img'   => $u . '/2026/09/GWM-POER-BLACK.jpg',
		'power' => array( 'Diesel', 'ICE' ),
		'link'  => '/poer/',
	),
	array(
		'name'  => 'ORA',
		'tag'   => 'Pure electric, pure character',
		'img'   => $u . '/2026/07/GWM-ORA-1.jpg',
		'power' => array( 'BEV' ),
		'link'  => '/ora-5/',
	),
);

// Alternating spotlight tiles.
$tiles = array(
	array(
		'name'  => 'HAVAL H6',
		'kick'  => 'Hybrid SUV',
		'desc'  => 'The intelligent hybrid SUV that balances efficiency, space and technology for the everyday drive.',
		'img'   => $u . '/2026/07/GWM-HAVAL-H6.jpg',
		'price' => 'From $34,900',
		'link'  => '/haval-h6/',
	),
	array(
		'name'  => 'TANK 500',
		'kick'  => 'Luxury Off-Road SUV',
		'desc'  => 'Bold design, premium comfort and serious off-road capability for those who demand more from every journey.',
		'img'   => $u . '/2026/08/GWM-TANK-500-POST-2-1536x788.jpg',
		'price' => 'From $66,900',
		'link'  => '/tank-500/',
	),
	array(
		'name'  => 'POER P300',
		'kick'  => 'Lifestyle Pickup',
		'desc'  => 'A rugged, refined pickup built for work and adventure, with the strength to take on Ethiopia\'s toughest terrain.',
		'img'   => $u . '/2026/07/GWM-Poer-P500-post.jpg',
		'price' => 'From $38,900',
		'link'  => '/poer/',
	),
);

// SVG icon helper.
if ( ! function_exists( 'gwm_icon' ) ) :
function gwm_icon( $name ) {
	$icons = array(
		'calendar' => '<path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/>',
		'wrench'   => '<path d="M14.7 6.3a4 4 0 0 0-5.2 5.2L3 18l3 3 6.5-6.5a4 4 0 0 0 5.2-5.2l-2.8 2.8-2.2-.6-.6-2.2 2.9-2.8Z"/>',
		'pin'      => '<path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
		'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/>',
		'chat'     => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 20l1-4.2A8.4 8.4 0 1 1 21 11.5Z"/>',
		'shield'   => '<path d="M12 2 4 5v6c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10V5l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
		'cog'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 6.6 19l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 13.4H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.9 7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 3.6V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',
		'bolt'     => '<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z"/>',
		'mountain' => '<path d="m3 20 6-10 4 6 2-3 6 7H3Z"/>',
		'parts'    => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
		'star'     => '<path d="m12 3 2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8L3.5 9.2l5.9-.9L12 3Z"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
	);
	$p = isset( $icons[ $name ] ) ? $icons[ $name ] : '';
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
endif;

$nav_items = array(
	'Vehicles'          => '#vehicles',
	'Tech &amp; Innovation' => '#technology',
	'Aftersales'        => '#aftersales',
	'About us'          => '#about',
	'Find Us'           => '#visit',
);

$wa = 'https://wa.me/251911000000'; // TODO: confirm real WhatsApp number.

/*
 * Fully self-contained document. We intentionally do NOT call get_header()
 * so that neither Astra nor Themify inject their own header/chrome onto the
 * GWM landing page. wp_head()/wp_footer() still fire for required assets.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'gwm-home-body' ); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
?>
<div class="gwm">

	<!-- ===================== NAVIGATION ===================== -->
	<header class="gwm-nav" id="gwmNav">
		<div class="gwm-nav__inner">
			<a class="gwm-nav__brand" href="/gwm-ethiopia/" aria-label="GWM Ethiopia — Home">
				<img src="<?php echo esc_url( $u ); ?>/2026/10/GWM-logo-red.png" alt="GWM" />
			</a>

			<nav class="gwm-nav__center" aria-label="Primary">
				<?php foreach ( $nav_items as $label => $href ) : ?>
					<a href="<?php echo esc_attr( $href ); ?>"><?php echo wp_kses_post( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="gwm-nav__right">
				<a class="gwm-nav__hagbes" href="/" aria-label="HAGBES — Home">
					<img src="<?php echo esc_url( $u ); ?>/2026/07/cropped-hagbes-logo-raw-new-landscape-white-web.png" alt="HAGBES" />
				</a>
				<button class="gwm-burger" id="gwmBurger" type="button" aria-label="Menu" aria-expanded="false">
					<span></span><span></span><span></span>
				</button>
			</div>
		</div>

		<!-- Mobile drawer -->
		<div class="gwm-drawer" id="gwmDrawer" aria-hidden="true">
			<?php foreach ( $nav_items as $label => $href ) : ?>
				<a href="<?php echo esc_attr( $href ); ?>"><?php echo wp_kses_post( $label ); ?></a>
			<?php endforeach; ?>
		</div>
	</header>

	<!-- ===================== HERO ===================== -->
	<section class="gwm-hero" id="home">

		<!-- Video banner (no overlay content) -->
		<div class="gwm-hero__media">
			<video class="gwm-hero__video" autoplay muted loop playsinline preload="auto"
				data-tf-not-load
				poster="<?php echo esc_url( $u ); ?>/2026/07/tank-500-a-1600x808.jpg">
				<source src="<?php echo esc_url( $u ); ?>/2026/10/tank500-hev-video-new.mp4" type="video/mp4" />
			</video>
		</div>

		<!-- Hero content, below the video -->
		<div class="gwm-hero__below">
			<div class="gwm-wrap gwm-hero__content">
				<div class="gwm-hero__copy">
					<p class="gwm-eyebrow">GWM &nbsp;·&nbsp; Ethiopia</p>
					<h1 class="gwm-hero__title">GO BEYOND<br>EVERY JOURNEY</h1>
					<p class="gwm-hero__sub">Explore the world of GWM. Built for more. Built for you.</p>
					<div class="gwm-hero__cta">
						<a class="gwm-btn gwm-btn--red" href="#vehicles">Explore Vehicles</a>
						<a class="gwm-btn gwm-btn--ghost" href="#visit">Find a Showroom</a>
					</div>
				</div>

				<?php
				// Hero price is pulled from the Tank 500 page's admin-managed price field (optional).
				$gwm_tank      = get_page_by_path( 'tank-500' );
				$gwm_tank_id   = $gwm_tank ? $gwm_tank->ID : 0;
				$gwm_hero_show = $gwm_tank_id && '0' !== (string) get_post_meta( $gwm_tank_id, '_gwm_show_price', true );
				$gwm_hero_fig  = $gwm_tank_id ? trim( (string) get_post_meta( $gwm_tank_id, '_gwm_price', true ) ) : '';
				$gwm_hero_pre  = $gwm_tank_id ? trim( (string) get_post_meta( $gwm_tank_id, '_gwm_price_prefix', true ) ) : '';
				if ( '' === $gwm_hero_pre ) {
					$gwm_hero_pre = 'From';
				}
				?>
				<aside class="gwm-hero__model">
					<span class="gwm-hero__model-name">TANK 500</span>
					<span class="gwm-hero__model-type">Luxury Off-Road SUV</span>
					<?php if ( $gwm_hero_show && '' !== $gwm_hero_fig ) : ?>
						<span class="gwm-hero__model-from"><?php echo esc_html( strtoupper( $gwm_hero_pre ) ); ?></span>
						<span class="gwm-hero__model-price"><?php echo esc_html( $gwm_hero_fig ); ?></span>
						<span class="gwm-hero__model-note">*T&amp;Cs apply</span>
					<?php endif; ?>
				</aside>
			</div>

			<!-- Quick actions -->
			<div class="gwm-wrap">
				<div class="gwm-quick">
					<a href="#visit"><?php echo gwm_icon( 'calendar' ); ?><span><strong>Book a Test Drive</strong>Get behind the wheel</span></a>
					<a href="/service/"><?php echo gwm_icon( 'wrench' ); ?><span><strong>Book a Service</strong>Fast. Easy. Reliable.</span></a>
					<a href="#visit"><?php echo gwm_icon( 'pin' ); ?><span><strong>Find a Showroom</strong>Find us near you</span></a>
					<a href="#visit"><?php echo gwm_icon( 'phone' ); ?><span><strong>Contact Us</strong>We're here to help</span></a>
					<a href="<?php echo esc_url( $wa ); ?>"><?php echo gwm_icon( 'chat' ); ?><span><strong>WhatsApp Us</strong>Chat with our team</span></a>
				</div>
			</div>
		</div>
	</section>

	<!-- ===================== EXPLORE OUR BRANDS ===================== -->
	<section class="gwm-section gwm-section--dark" id="vehicles">
		<div class="gwm-wrap">
			<div class="gwm-section__head">
				<h2>Explore our brands</h2>
				<a class="gwm-link" href="#vehicles">View all vehicles <?php echo gwm_icon( 'arrow' ); ?></a>
			</div>
			<div class="gwm-brands">
				<?php foreach ( $brands as $b ) : ?>
					<a class="gwm-brand" href="<?php echo esc_url( $b['link'] ); ?>" style="--bg:url('<?php echo esc_url( $b['img'] ); ?>')">
						<div class="gwm-brand__body">
							<h3><?php echo esc_html( $b['name'] ); ?></h3>
							<p><?php echo esc_html( $b['tag'] ); ?></p>
							<div class="gwm-badges">
								<?php foreach ( $b['power'] as $p ) : ?>
									<span class="gwm-badge"><?php echo esc_html( $p ); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ===================== SPOTLIGHT TILES ===================== -->
	<section class="gwm-section gwm-section--pad0">
		<?php foreach ( $tiles as $i => $t ) : ?>
			<div class="gwm-spot <?php echo ( $i % 2 === 1 ) ? 'gwm-spot--rev gwm-spot--light' : 'gwm-spot--dark'; ?>">
				<div class="gwm-spot__media" style="background-image:url('<?php echo esc_url( $t['img'] ); ?>')"></div>
				<div class="gwm-spot__text">
					<p class="gwm-kicker"><?php echo esc_html( $t['kick'] ); ?></p>
					<h2><?php echo esc_html( $t['name'] ); ?></h2>
					<p class="gwm-spot__desc"><?php echo esc_html( $t['desc'] ); ?></p>
					<?php
					// Price from the model page's admin-managed field (hidden when unset).
					$gwm_tile_price = gwm_get_price( trim( wp_parse_url( $t['link'], PHP_URL_PATH ), '/' ) );
					if ( '' !== $gwm_tile_price ) :
						?>
						<p class="gwm-spot__price"><?php echo esc_html( $gwm_tile_price ); ?></p>
					<?php endif; ?>
					<a class="gwm-btn gwm-btn--red" href="<?php echo esc_url( $t['link'] ); ?>">Explore <?php echo esc_html( $t['name'] ); ?></a>
				</div>
			</div>
		<?php endforeach; ?>
	</section>

	<!-- ===================== TECHNOLOGY ===================== -->
	<section class="gwm-section gwm-section--dark" id="technology">
		<div class="gwm-wrap">
			<div class="gwm-section__head gwm-section__head--center">
				<p class="gwm-kicker">Tech &amp; Innovation</p>
				<h2>Engineered to go further</h2>
			</div>
			<div class="gwm-grid3">
				<div class="gwm-tech">
					<span class="gwm-tech__ic"><?php echo gwm_icon( 'bolt' ); ?></span>
					<h3>Hi4 Hybrid Platform</h3>
					<p>Intelligent hybrid 4WD that blends electric torque with efficiency for seamless, powerful everyday driving.</p>
				</div>
				<div class="gwm-tech">
					<span class="gwm-tech__ic"><?php echo gwm_icon( 'mountain' ); ?></span>
					<h3>Hi4-T Off-Road</h3>
					<p>TANK's plug-in hybrid off-road system delivers low-range capability, torque on demand and true all-terrain confidence.</p>
				</div>
				<div class="gwm-tech">
					<span class="gwm-tech__ic"><?php echo gwm_icon( 'shield' ); ?></span>
					<h3>ADAS + ANCAP Safety</h3>
					<p>Advanced driver assistance and a body engineered around independent crash-test standards to protect every journey.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- ===================== WHY HAGBES / AFTERSALES ===================== -->
	<section class="gwm-section gwm-section--light" id="aftersales">
		<div class="gwm-wrap">
			<div class="gwm-section__head gwm-section__head--center">
				<p class="gwm-kicker gwm-kicker--dark">Aftersales you can count on</p>
				<h2>Why HAGBES</h2>
				<p class="gwm-section__lead">Our commitment doesn't end when you drive away. We're with you for the long run.</p>
			</div>
			<div class="gwm-grid3">
				<div class="gwm-why">
					<span class="gwm-why__ic"><?php echo gwm_icon( 'shield' ); ?></span>
					<h3>7-Year Warranty</h3>
					<p>Unlimited-km peace of mind, backed by Ethiopia's widest authorised GWM dealer network.</p>
				</div>
				<div class="gwm-why">
					<span class="gwm-why__ic"><?php echo gwm_icon( 'parts' ); ?></span>
					<h3>100% Genuine Parts</h3>
					<p>Protect your investment with genuine GWM parts, in stock and fitted by certified technicians.</p>
				</div>
				<div class="gwm-why">
					<span class="gwm-why__ic"><?php echo gwm_icon( 'cog' ); ?></span>
					<h3>Expert Aftersales</h3>
					<p>A GWM-trained workshop — hybrid &amp; EV care, body repair and the best paint shop in Ethiopia.</p>
				</div>
			</div>
			<div class="gwm-center">
				<a class="gwm-btn gwm-btn--dark" href="/service/">Explore Aftersales</a>
			</div>
		</div>
	</section>

	<!-- ===================== VISIT US ===================== -->
	<section class="gwm-section gwm-section--dark gwm-visit" id="visit">
		<div class="gwm-wrap gwm-visit__grid">
			<div class="gwm-visit__media" style="background-image:url('<?php echo esc_url( $u ); ?>/2026/08/gwm-service-and-parts.jpg')"></div>
			<div class="gwm-visit__text">
				<p class="gwm-kicker" id="about">Visit us</p>
				<h2>Discover GWM in Addis&nbsp;Ababa</h2>
				<p>Experience the full GWM range at a HAGBES showroom. Our team is ready to walk you through every model, arrange a test drive, and tailor the right vehicle to you.</p>
				<ul class="gwm-visit__info">
					<li><?php echo gwm_icon( 'pin' ); ?> Addis Ababa, Ethiopia</li>
					<li><?php echo gwm_icon( 'phone' ); ?> <a href="tel:+251911000000">+251 911 000 000</a></li>
					<li><?php echo gwm_icon( 'star' ); ?> Official GWM Partner · Since 1957</li>
				</ul>
				<div class="gwm-hero__cta">
					<a class="gwm-btn gwm-btn--red" href="/services/">Contact Us</a>
					<a class="gwm-btn gwm-btn--ghost" href="<?php echo esc_url( $wa ); ?>">WhatsApp Us</a>
				</div>
			</div>
		</div>
	</section>

	<!-- ===================== FOOTER ===================== -->
	<footer class="gwm-foot">
		<div class="gwm-wrap gwm-foot__inner">
			<img class="gwm-foot__logo" src="<?php echo esc_url( $u ); ?>/2026/10/GWM-AND-HAGBES-LOGO.png" alt="GWM" />
			<nav class="gwm-foot__nav" aria-label="Footer">
				<a href="#vehicles">Vehicles</a>
				<a href="#technology">Tech &amp; Innovation</a>
				<a href="#aftersales">Aftersales</a>
				<a href="/about/">About us</a>
				<a href="/services/">Find Us</a>
			</nav>
			<p class="gwm-foot__copy">© <?php echo esc_html( gmdate( 'Y' ) ); ?> HAGBES · Official GWM Partner in Ethiopia. All rights reserved.</p>
		</div>
	</footer>

</div><!-- .gwm -->
<?php wp_footer(); ?>
</body>
</html>
<?php
// End of GWM self-contained template.
