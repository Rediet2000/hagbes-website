<?php
/**
 * Astra Child (HAGBES / GWM) functions.
 *
 * Only loads custom assets on the GWM templates so the rest of the
 * corporate site keeps rendering exactly as it did under Astra.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASTRA_CHILD_VER', '1.0.7' );

/**
 * Enqueue the parent Astra stylesheet (child inherits the corporate styling).
 */
function astra_child_enqueue_parent() {
	wp_enqueue_style(
		'astra-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		defined( 'ASTRA_THEME_VERSION' ) ? ASTRA_THEME_VERSION : null
	);
}
add_action( 'wp_enqueue_scripts', 'astra_child_enqueue_parent', 5 );

/**
 * Enqueue the GWM homepage assets only on pages using the GWM Home template.
 */
/**
 * True when the current view is a GWM brand "car page" post (Themify-built
 * single post that we render through the vehicle template).
 */
function astra_child_is_gwm_brand_post() {
	if ( ! is_singular( 'post' ) ) {
		return false;
	}
	$pid = get_queried_object_id();
	return ( $pid && get_post_meta( $pid, '_themify_builder_settings_json', true ) );
}

function astra_child_gwm_assets() {
	$is_home    = is_page_template( 'template-gwm-home.php' );
	$is_vehicle = is_page_template( 'template-gwm-vehicle.php' ) || astra_child_is_gwm_brand_post();

	if ( ! $is_home && ! $is_vehicle ) {
		return;
	}

	// Google Fonts: Inter (body) + Oswald (display headings).
	wp_enqueue_style(
		'gwm-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Oswald:wght@400;500;600;700&display=swap',
		array(),
		null
	);

	if ( $is_home ) {
		wp_enqueue_style(
			'gwm-home',
			get_stylesheet_directory_uri() . '/assets/css/gwm-home.css',
			array( 'gwm-fonts' ),
			ASTRA_CHILD_VER
		);
		wp_enqueue_script(
			'gwm-home',
			get_stylesheet_directory_uri() . '/assets/js/gwm-home.js',
			array(),
			ASTRA_CHILD_VER,
			true
		);
	}

	if ( $is_vehicle ) {
		wp_enqueue_style(
			'gwm-vehicle',
			get_stylesheet_directory_uri() . '/assets/css/gwm-vehicle.css',
			array( 'gwm-fonts' ),
			ASTRA_CHILD_VER
		);
	}
}
add_action( 'wp_enqueue_scripts', 'astra_child_gwm_assets', 20 );

/* =====================================================================
 * Optional, admin-managed vehicle price
 * ---------------------------------------------------------------------
 * Meta:
 *   _gwm_price        -> the figure, e.g. "$66,900" (empty = no price shown)
 *   _gwm_price_prefix -> label before it, default "From"
 *   _gwm_show_price   -> "1" show (default), "0" hide even if a price is set
 * Use [gwm_price] in Themify content, or gwm_get_price()/gwm_price_html()
 * in templates. Editable per page under the "GWM Vehicle Price" box.
 * ===================================================================== */

/**
 * Resolve a price for a page/post. Returns "" when nothing should show,
 * so the price is genuinely optional.
 *
 * @param int|string $page  Post ID or page slug/path. Default: current post.
 * @return string Display string such as "From $66,900", or "".
 */
function gwm_get_price( $page = 0 ) {
	if ( 0 === $page || '' === $page ) {
		$post_id = get_queried_object_id();
	} elseif ( is_numeric( $page ) ) {
		$post_id = (int) $page;
	} else {
		$p       = get_page_by_path( sanitize_title( $page ) );
		$post_id = $p ? $p->ID : 0;
	}
	if ( ! $post_id ) {
		return '';
	}

	if ( '0' === (string) get_post_meta( $post_id, '_gwm_show_price', true ) ) {
		return '';
	}
	$price = trim( (string) get_post_meta( $post_id, '_gwm_price', true ) );
	if ( '' === $price ) {
		return '';
	}
	$prefix = trim( (string) get_post_meta( $post_id, '_gwm_price_prefix', true ) );
	if ( '' === $prefix ) {
		$prefix = 'From';
	}
	return trim( $prefix . ' ' . $price );
}

/**
 * Convenience markup wrapper (returns "" when there is no price).
 */
function gwm_price_html( $page = 0, $class = 'gwm-price' ) {
	$txt = gwm_get_price( $page );
	if ( '' === $txt ) {
		return '';
	}
	return '<span class="' . esc_attr( $class ) . '">' . esc_html( $txt ) . '</span>';
}

/**
 * [gwm_price] shortcode — drop into Themify content where a price should
 * appear. Shows nothing when the admin leaves the price blank or hides it.
 * Attributes: id="123" (another page), prefix="From", class="gwm-price".
 */
function gwm_price_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'     => 0,
			'class'  => 'gwm-price',
		),
		$atts,
		'gwm_price'
	);
	return gwm_price_html( $atts['id'] ? (int) $atts['id'] : 0, $atts['class'] );
}
add_shortcode( 'gwm_price', 'gwm_price_shortcode' );

/**
 * Register the price meta so it is sanitised and REST-exposed.
 */
function astra_child_register_price_meta() {
	foreach ( array( 'page', 'post' ) as $type ) {
		register_post_meta(
			$type,
			'_gwm_price',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_post_meta(
			$type,
			'_gwm_price_prefix',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_post_meta(
			$type,
			'_gwm_show_price',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'astra_child_register_price_meta' );

/**
 * Classic-editor meta box so an admin can manage the price without code.
 */
function astra_child_price_metabox() {
	add_meta_box(
		'gwm_price_box',
		'GWM Vehicle Price',
		'astra_child_price_metabox_render',
		array( 'page', 'post' ),
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'astra_child_price_metabox' );

function astra_child_price_metabox_render( $post ) {
	wp_nonce_field( 'gwm_price_save', 'gwm_price_nonce' );
	$price  = get_post_meta( $post->ID, '_gwm_price', true );
	$prefix = get_post_meta( $post->ID, '_gwm_price_prefix', true );
	$show   = get_post_meta( $post->ID, '_gwm_show_price', true );
	$checked = ( '0' !== (string) $show ); // default on
	?>
	<p>
		<label for="gwm_price"><strong>Starting price</strong></label>
		<input type="text" id="gwm_price" name="gwm_price" value="<?php echo esc_attr( $price ); ?>"
			placeholder="e.g. $66,900" style="width:100%;" />
		<span style="color:#666;">Leave blank to hide the price.</span>
	</p>
	<p>
		<label for="gwm_price_prefix">Label before price</label>
		<input type="text" id="gwm_price_prefix" name="gwm_price_prefix" value="<?php echo esc_attr( $prefix ); ?>"
			placeholder="From" style="width:100%;" />
	</p>
	<p>
		<label>
			<input type="checkbox" name="gwm_show_price" value="1" <?php checked( $checked ); ?> />
			Show price on the site
		</label>
	</p>
	<p style="color:#666;">Use <code>[gwm_price]</code> inside the page builder to place it.</p>
	<?php
}

function astra_child_price_metabox_save( $post_id ) {
	if ( ! isset( $_POST['gwm_price_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['gwm_price_nonce'] ), 'gwm_price_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_gwm_price', sanitize_text_field( wp_unslash( $_POST['gwm_price'] ?? '' ) ) );
	update_post_meta( $post_id, '_gwm_price_prefix', sanitize_text_field( wp_unslash( $_POST['gwm_price_prefix'] ?? '' ) ) );
	update_post_meta( $post_id, '_gwm_show_price', isset( $_POST['gwm_show_price'] ) ? '1' : '0' );
}
add_action( 'save_post', 'astra_child_price_metabox_save' );

/**
 * Make the custom page template selectable in the editor and discoverable
 * even if the theme's template header is cached.
 */
function astra_child_register_templates( $templates ) {
	$templates['template-gwm-home.php']    = 'GWM Home (Dark)';
	$templates['template-gwm-vehicle.php'] = 'GWM Vehicle (Dark)';
	return $templates;
}
add_filter( 'theme_page_templates', 'astra_child_register_templates' );

/**
 * The GWM templates this child theme provides.
 */
function astra_child_gwm_template_slugs() {
	return array( 'template-gwm-home.php', 'template-gwm-vehicle.php' );
}

/**
 * Force a GWM template to win over page builders (Themify) that hijack
 * template_include. Runs at the very end of the filter chain so our file
 * is what actually renders when a page is assigned a GWM template.
 */
function astra_child_force_gwm_template( $template ) {
	// Pages: honor the assigned GWM template (home or vehicle).
	if ( is_page() ) {
		$slug = (string) get_page_template_slug( get_queried_object_id() );
		if ( in_array( $slug, astra_child_gwm_template_slugs(), true ) ) {
			$custom = locate_template( $slug );
			if ( $custom ) {
				return $custom;
			}
		}
	}

	/*
	 * Single posts: the GWM brand "car pages" (HAVAL, TANK, POER, ORA,
	 * Vehicles) are Themify-built posts. Render them through the clean GWM
	 * vehicle template so they drop the default single-post chrome
	 * (Previous/Next Post navigation, white footer) and match the vehicle
	 * pages. A normal blog post (no Themify builder) is left untouched.
	 */
	if ( is_singular( 'post' ) ) {
		$pid = get_queried_object_id();
		if ( $pid && get_post_meta( $pid, '_themify_builder_settings_json', true ) ) {
			$custom = locate_template( 'template-gwm-vehicle.php' );
			if ( $custom ) {
				return $custom;
			}
		}
	}

	return $template;
}
add_filter( 'template_include', 'astra_child_force_gwm_template', PHP_INT_MAX );
