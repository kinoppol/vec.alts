<?php
/**
 * Boots the application: compatibility shims, configuration, session,
 * database, then the shared service objects.
 *
 * Targets PHP 5.4 (CentOS 7) through PHP 8.x (XAMPP). Nothing here may use
 * syntax newer than 5.4.
 */

define('VEC_ROOT', dirname(__DIR__));
define('VEC_APP', VEC_ROOT . '/app');
define('VEC_VERSION', '1.0.0');

// Report everything internally; whether it reaches the browser is decided
// once the config is loaded. E_STRICT folds into E_ALL from PHP 5.4 onwards.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require VEC_APP . '/compat.php';
require VEC_APP . '/helpers.php';
require VEC_APP . '/config_io.php';
require VEC_APP . '/Database.php';
require VEC_APP . '/Schema.php';
require VEC_APP . '/Migrator.php';
require VEC_APP . '/Auth.php';
require VEC_APP . '/View.php';
require VEC_APP . '/Repository.php';

// Before any output, including the redirect below and the fatal-error pages.
vec_send_charset();

$config = vec_load_config();

if ($config === null || empty($config['installed'])) {
    // Not installed yet: everything except the installer goes to install.php.
    if (!defined('VEC_INSTALLER')) {
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        header('Location: ' . $base . '/install.php');
        exit;
    }
    return;
}

$config = vec_normalise_config($config);

// ------------------------------------------------------------- environment
if (!empty($config['app']['debug'])) {
    ini_set('display_errors', '1');
}
if (!empty($config['app']['timezone'])) {
    date_default_timezone_set($config['app']['timezone']);
} elseif (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Bangkok');
}
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

vec_start_session($config['session']);

// ----------------------------------------------------------------- services
try {
    $pdo = Database::connect($config['db']);
    Database::setShared($pdo);
} catch (PDOException $e) {
    app_log('DB connect failed: ' . $e->getMessage());
    vec_fatal(
        'เชื่อมต่อฐานข้อมูลไม่สำเร็จ',
        $e->getMessage(),
        !empty($config['app']['debug'])
    );
}

$prefix = $config['db']['prefix'];
$auth = new Auth($pdo, $prefix);
$repo = new Repository($pdo, $prefix);
$view = new View(VEC_ROOT . '/views');

$view->share('config', $config);
$view->share('auth', $auth);
$view->share('repo', $repo);
$view->share('appName', $config['app']['name']);
