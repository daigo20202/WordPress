<?php
/**
 * Implements an optional custom header for Twenty Twelve
 *
 * See https://codex.wordpress.org/Custom_Headers
 *
 * @package WordPress
 * @subpackage Twenty_Twelve
 * @since Twenty Twelve 1.0
 */

/**
 * Sets up the WordPress core custom header arguments and settings.
 *
 * @uses add_theme_support() to register support for 3.4 and up.
 * @uses twentytwelve_header_style() to style front end.
 * @uses twentytwelve_admin_header_style() to style wp-admin form.
 * @uses twentytwelve_admin_header_image() to add custom markup to wp-admin form.
 *
 * @since Twenty Twelve 1.0
 */
function twentytwelve_custom_header_setup() {
	$args = array(
		// Text color and image (empty to use none).
		'default-text-color'     => '515151',
		'default-image'          => '',

		// Set height and width, with a maximum value for the width.
		'height'                 => 250,
		'width'                  => 960,
		'max-width'              => 2000,

		// Support flexible height and width.
		'flex-height'            => true,
		'flex-width'             => true,

		// Random image rotation off by default.
		'random-default'         => false,

		// Callbacks for styling the header and the admin preview.
		'wp-head-callback'       => 'twentytwelve_header_style',
		'admin-head-callback'    => 'twentytwelve_admin_header_style',
		'admin-preview-callback' => 'twentytwelve_admin_header_image',
	);

	add_theme_support( 'custom-header', $args );
}
add_action( 'after_setup_theme', 'twentytwelve_custom_header_setup' );

/**
 * Loads our special font CSS file.
 *
 * @since Twenty Twelve 1.2
 */
function twentytwelve_custom_header_fonts() {
	$font_url = twentytwelve_get_font_url();
	if ( ! empty( $font_url ) ) {
		wp_enqueue_style( 'twentytwelve-fonts', esc_url_raw( $font_url ), array(), null );
	}
}
add_action( 'admin_print_styles-appearance_page_custom-header', 'twentytwelve_custom_header_fonts' );

/**
 * Styles the header text displayed on the blog.
 *
 * get_header_textcolor() options: 515151 is default, hide text (returns 'blank'), or any hex value.
 *
 * @since Twenty Twelve 1.0
 */
function twentytwelve_header_style() {
	$text_color = get_header_textcolor();

	// If no custom options for text are set, let's bail.
	if ( get_theme_support( 'custom-header', 'default-text-color' ) === $text_color ) {
		return;
	}

	// If we get this far, we have custom styles.
	?>
	<style type="text/css" id="twentytwelve-header-css">
	<?php
		// Has the text been hidden?
	if ( ! display_header_text() ) :
		?>
	.site-title,
	.site-description {
		position: absolute;
		clip-path: inset(50%);
	}
		<?php
		// If the user has set a custom color for the text, use that.
		else :
			?>
		.site-header h1 a,
		.site-header h2 {
			color: #<?php echo $text_color; ?>;
		}
	<?php endif; ?>
	</style>
	<?php
}

/**
 * Styles the header image displayed on the Appearance > Header admin panel.
 *
 * @since Twenty Twelve 1.0
 */
function twentytwelve_admin_header_style() {
	?>
	<style type="text/css" id="twentytwelve-admin-header-css">
	.appearance_page_custom-header #headimg {
		border: none;
		font-family: "Open Sans", Helvetica, Arial, sans-serif;
	}
	#headimg h1,
	#headimg h2 {
		line-height: 1.84615;
		margin: 0;
		padding: 0;
	}
	#headimg h1 {
		font-size: 26px;
	}
	#headimg h1 a {
		color: #515151;
		text-decoration: none;
	}
	#headimg h1 a:hover {
		color: #21759b !important; /* Has to override custom inline style. */
	}
	#headimg h2 {
		color: #757575;
		font-size: 13px;
		margin-bottom: 24px;
	}
	#headimg img {
		max-width: <?php echo get_theme_support( 'custom-header', 'max-width' ); ?>px;
	}
	</style>
	<?php
}

/**
 * Outputs markup to be displayed on the Appearance > Header admin panel.
 *
 * This callback overrides the default markup displayed there.
 *
 * @since Twenty Twelve 1.0
 */
function twentytwelve_admin_header_image() {
	$style = 'color: #' . get_header_textcolor() . ';';
	if ( ! display_header_text() ) {
		$style = 'display: none;';
	}
	?>
	<div id="headimg">
		<h1 class="displaying-header-text"><a id="name" style="<?php echo esc_attr( $style ); ?>" onclick="return false;" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></h1>
		<h2 id="desc" class="displaying-header-text" style="<?php echo esc_attr( $style ); ?>"><?php bloginfo( 'description' ); ?></h2>
		<?php
		$header_image = get_header_image();
		if ( ! empty( $header_image ) ) :
			?>
			<img src="<?php echo esc_url( $header_image ); ?>" class="header-image" width="<?php echo esc_attr( get_custom_header()->width ); ?>" height="<?php echo esc_attr( get_custom_header()->height ); ?>" alt="" />
		<?php endif; ?>
	</div>
	<?php
}


/**
 * Outputs markup to be displayed.
 *
 * @since Twenty Twelve 4.1
 */
function twentytwelve_header_image() {
	$custom_header = get_custom_header();
	$attrs         = array(
		'alt'    => get_bloginfo( 'name', 'display' ),
		'class'  => 'header-image',
		'height' => $custom_header->height,
		'width'  => $custom_header->width,
	);

	if ( function_exists( 'the_header_image_tag' ) ) {
		the_header_image_tag( $attrs );
		return;
	}
	?>
	<img src="<?php header_image(); ?>" class="<?php echo esc_attr( $attrs['class'] ); ?>" width="<?php echo esc_attr( $attrs['width'] ); ?>" height="<?php echo esc_attr( $attrs['height'] ); ?>" alt="<?php echo esc_attr( $attrs['alt'] ); ?>" />
	<?php
}
