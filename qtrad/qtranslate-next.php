<?php
/** Compatibility bootstrap for scripts that included the former entry point.
 * Activate qtrad.php in WordPress after replacing the former build.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/qtrad.php';
