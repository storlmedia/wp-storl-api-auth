<?php

/**
 * Plugin Name: Storl Wordpress API Auth
 * Description: Storl Wordpress API Auth
 * Version: 1.0.0
 * Text Domain: storl
 * Domain Path: /i18n/languages
 * Author: storlmedia
 *
 * @package storl-api-auth
 */

defined('ABSPATH') || exit;

define('STORL_API_AUTH_PLUGIN_ABSPATH', __DIR__);
define('STORL_API_AUTH_PLUGIN_FILE', __FILE__);
define('STORL_API_AUTH_PLUGIN_VERSION', '1.0.0');

require_once __DIR__ . '/vendor/autoload.php';

\Storl\WpApiAuth\Plugin::instance();
