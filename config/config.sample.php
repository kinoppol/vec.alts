<?php
/**
 * Template for config/config.php.
 *
 * install.php writes the real file. Copy this by hand only if you prefer to
 * configure the application without running the installer.
 */

// Refuse to run when requested directly over HTTP.
if (!defined('VEC_ROOT')) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

return array(

    // ---------------------------------------------------------------- database
    'db' => array(
        'host'   => 'localhost',
        'port'   => 3306,
        'name'   => 'vec_alumni',
        'user'   => 'root',
        'pass'   => '',
        // Leave empty on Windows/XAMPP. On CentOS 7 you may prefer
        // '/var/lib/mysql/mysql.sock' instead of a TCP connection.
        'socket' => '',
        // Prefix lets several installations share one database.
        'prefix' => 'va_',
    ),

    // ------------------------------------------------------------------- site
    'app' => array(
        'name'     => 'ระบบติดตามศิษย์เก่า',
        'timezone' => 'Asia/Bangkok',
        // 'production' hides error detail; 'development' shows it.
        'env'      => 'production',
        'debug'    => false,
    ),

    // ---------------------------------------------------------------- session
    'session' => array(
        'name'     => 'VECALTS',
        'lifetime' => 7200,
        // Set to true once the site is served over HTTPS.
        'secure'   => false,
    ),

    // Written by the installer. Its presence makes install.php ask for the
    // central administrator password before it will do anything.
    'installed'    => false,
    'installed_at' => null,
    'version'      => '1.0.0',
);
