<?php
// Only the matrix runner's freshly created disposable installation is accepted.
$root = isset($argv[1]) ? $argv[1] : '';
if (!is_file($root . '/.qtrad-disposable') || !is_file($root . '/wp-config.php')) {
    fwrite(STDERR, "Refusing to install outside a matrix fixture.\n");
    exit(2);
}
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8931';
$_SERVER['REQUEST_URI'] = '/';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (is_blog_installed()) {
    fwrite(STDERR, "Matrix database must be empty.\n");
    exit(2);
}
wp_install('qTrad disposable tests', 'qtrad_admin', 'qtrad@example.invalid', true, '', 'qtrad-disposable-admin-only');
update_option('home', 'http://127.0.0.1:8931');
update_option('siteurl', 'http://127.0.0.1:8931');
echo "Installed disposable WordPress.\n";
