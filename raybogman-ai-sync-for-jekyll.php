<?php
/**
 * Plugin Name: Ray Bogman AI Sync for Jekyll & GitHub Pages
 * Description: AI-assisted sync from WordPress to Jekyll static sites on GitHub Pages. Converts posts to Markdown with YAML front matter; optional Claude/OpenAI generation of SEO descriptions and image alt text. Not affiliated with Jekyll or GitHub.
 * Version: 7.3.0
 * Author: Ray Bogman
 * License: GPL-2.0-or-later
 * Text Domain: raybogman-ai-sync-for-jekyll
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPJS_VERSION', '7.3.0' );
define( 'WPJS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPJS_URL', plugin_dir_url( __FILE__ ) );

require_once WPJS_PATH . 'includes/class-settings.php';
require_once WPJS_PATH . 'includes/class-converter.php';
require_once WPJS_PATH . 'includes/class-github-client.php';
require_once WPJS_PATH . 'includes/class-github-oauth.php';
require_once WPJS_PATH . 'includes/class-style-detector.php';
require_once WPJS_PATH . 'includes/class-sync-log.php';
require_once WPJS_PATH . 'includes/class-diff.php';
require_once WPJS_PATH . 'includes/class-ai-client.php';
require_once WPJS_PATH . 'includes/class-publisher.php';
require_once WPJS_PATH . 'includes/class-cron.php';
require_once WPJS_PATH . 'includes/class-puller.php';
require_once WPJS_PATH . 'includes/class-admin.php';
require_once WPJS_PATH . 'includes/class-articles-table.php';
require_once WPJS_PATH . 'includes/class-meta-box.php';

add_action( 'plugins_loaded', function () {
	new WPJS_GitHub_OAuth();
	new WPJS_Admin();
	new WPJS_Meta_Box();
	WPJS_Publisher::init();
	WPJS_Cron::init();
} );
