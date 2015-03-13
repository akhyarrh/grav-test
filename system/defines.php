<?php

// Some standard defines
define('GRAV', true);
define('GRAV_VERSION', '0.9.19');
define('DS', '/');

// Directories and Paths
if (!defined('GRAV_ROOT')) {
    define('GRAV_ROOT', getcwd());
}
define('ROOT_DIR', GRAV_ROOT . '/');
define('USER_PATH', 'user/');
define('USER_DIR', ROOT_DIR . USER_PATH);
define('SYSTEM_DIR', ROOT_DIR .'system/');
define('ASSETS_DIR', ROOT_DIR . 'assets/');
define('CACHE_DIR', ROOT_DIR . 'cache/');
define('IMAGES_DIR', ROOT_DIR . 'images/');
define('LOG_DIR', ROOT_DIR .'logs/');
define('ACCOUNTS_DIR', USER_DIR .'accounts/');
define('PAGES_DIR', USER_DIR .'pages/');

// DEPRECATED: Do not use!
define('DATA_DIR', USER_DIR .'data/');
define('LIB_DIR', SYSTEM_DIR .'src/');
define('PLUGINS_DIR', USER_DIR .'plugins/');
define('THEMES_DIR', USER_DIR .'themes/');
define('VENDOR_DIR', ROOT_DIR .'vendor/');
// END DEPRECATED

// Some extensions
define('CONTENT_EXT', '.md');
define('TEMPLATE_EXT', '.html.twig');
define('TWIG_EXT', '.twig');
define('PLUGIN_EXT', '.php');
define('YAML_EXT', '.yaml');

// Content types
define('RAW_CONTENT', 1);
define('TWIG_CONTENT', 2);
define('TWIG_CONTENT_LIST', 3);
define('TWIG_TEMPLATES', 4);
