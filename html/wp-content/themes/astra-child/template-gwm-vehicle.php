<?php
/**
 * Template Name: GWM Vehicle (Dark)
 *
 * Self-contained wrapper for the existing GWM vehicle pages. Renders the
 * page's Themify Builder content (which already contains the black GWM nav
 * and the model layout) WITHOUT the Astra HAGBES header/footer, then adds a
 * slim GWM footer. Assign this template to the GWM vehicle pages.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'gwm-vehicle-body' ); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}

/*
 * The Themify Builder content (black GWM nav + model layout) renders here.
 * Wrapped in .entry-content so Astra's content typography applies — these
 * pages were built in that context, and without it headings lose their
 * sizing/margins (e.g. the ORA text module overlapped its paragraph).
 */
while ( have_posts() ) :
	the_post();
	?>
	<div class="entry-content clear">
		<?php the_content(); ?>
	</div>
	<?php
endwhile;

/* Slim GWM footer (replaces the removed Astra footer). */
$u = '/wp-content/uploads';
?>
<footer class="gwm-vfoot">
	<div class="gwm-vfoot__inner">
		<img class="gwm-vfoot__logo" src="<?php echo esc_url( $u ); ?>/2026/10/GWM-AND-HAGBES-LOGO.png" alt="GWM" />
		<nav class="gwm-vfoot__nav" aria-label="Footer">
			<a href="/gwm-ethiopia/">Home</a>
			<a href="/haval-2/">Vehicles</a>
			<a href="/hagbes-care/">Aftersales</a>
			<a href="/about/">About</a>
			<a href="/services/">Contact</a>
		</nav>
		<p class="gwm-vfoot__copy">© <?php echo esc_html( gmdate( 'Y' ) ); ?> HAGBES · Official GWM Partner in Ethiopia.</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
<?php
// End of GWM vehicle template.
