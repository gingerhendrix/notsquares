<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes() ?>>
<head profile="http://gmpg.org/xfn/11">
	<title><?php wp_title( '-', true, 'right' ); echo wp_specialchars( get_bloginfo('name'), 1 ) ?></title>
	<meta http-equiv="content-type" content="<?php bloginfo('html_type') ?>; charset=<?php bloginfo('charset') ?>" />
	<link rel="stylesheet" type="text/css" href="<?php bloginfo('stylesheet_url') ?>" />
<?php wp_head() // For plugins ?>
	<link rel="alternate" type="application/rss+xml" href="<?php bloginfo('rss2_url') ?>" title="<?php printf( __( '%s latest posts', 'sandbox' ), wp_specialchars( get_bloginfo('name'), 1 ) ) ?>" />
	<link rel="alternate" type="application/rss+xml" href="<?php bloginfo('comments_rss2_url') ?>" title="<?php printf( __( '%s latest comments', 'sandbox' ), wp_specialchars( get_bloginfo('name'), 1 ) ) ?>" />
	<link rel="pingback" href="<?php bloginfo('pingback_url') ?>" />
	
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.3/jquery.min.js"></script>
	<script src="wp-content/themes/sandbox-notsquares/js/notsquares.js"></script>
</head>

<body class="<?php sandbox_body_class() ?>">

<div id="wrapper" class="hfeed">

  <div id="sun" ></div>
		
	<div id="header">
		<img id="logo_top" src="wp-content/themes/notsquares/images/xxxx_logo.png" width="160" height="160"></img>
		<img id="logotype_top" src="wp-content/themes/notsquares/images/notsquares_words.png" width="171" height="22"></img>
	</div>

	<!--
	<div id="header">
		<h1 id="blog-title"><span><a href="<?php bloginfo('home') ?>/" title="<?php echo wp_specialchars( get_bloginfo('name'), 1 ) ?>" rel="home"><?php bloginfo('name') ?></a></span></h1>
		<a  href="<?php bloginfo('home') ?>/" title="<?php echo wp_specialchars( get_bloginfo('name'), 1 ) ?>" rel="home">
		  <img class="heder-image" src="wp-content/themes/notsquares/images/logo.jpg"></img>
		</a>
		<div id="blog-description"><?php bloginfo('description') ?></div>
	</div> --> <!--  #header -->

  <!--
	<div id="access">
		<div class="skip-link"><a href="#content" title="<?php _e( 'Skip to content', 'sandbox' ) ?>"><?php _e( 'Skip to content', 'sandbox' ) ?></a></div>
		<?php sandbox_globalnav() ?>
	</div> --> <!-- #access -->
	
	<div id="bd">
		<div id="top_edge"></div>
		<div id="drop_shadow"></div>	
