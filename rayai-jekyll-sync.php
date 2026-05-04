<?php
/**
 * Plugin Name: RayAI – Jekyll Sync
 * Description: Push WordPress posts and pages to a Jekyll GitHub Pages repository as Markdown with YAML front matter.
 * Version: 3.3.0
 * Author: Ray Bogman
 * License: GPL-2.0-or-later
 * Text Domain: rayai-jekyll-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPJS_VERSION', '3.3.0' );
define( 'WPJS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPJS_URL', plugin_dir_url( __FILE__ ) );

require_once WPJS_PATH . 'includes/class-settings.php';
require_once WPJS_PATH . 'includes/class-converter.php';
require_once WPJS_PATH . 'includes/class-github-client.php';
require_once WPJS_PATH . 'includes/class-github-oauth.php';
require_once WPJS_PATH . 'includes/class-style-detector.php';
require_once WPJS_PATH . 'includes/class-publisher.php';
require_once WPJS_PATH . 'includes/class-admin.php';
require_once WPJS_PATH . 'includes/class-articles-table.php';
require_once WPJS_PATH . 'includes/class-meta-box.php';

add_action( 'plugins_loaded', function () {
	new WPJS_GitHub_OAuth();
	new WPJS_Admin();
	new WPJS_Meta_Box();
} );
