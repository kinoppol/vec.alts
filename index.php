<?php
/**
 * Front controller.
 *
 * Routing is done with a query string (index.php?r=exec/departments) rather
 * than URL rewriting, because mod_rewrite is not guaranteed to be enabled on
 * the production CentOS 7 host. The .htaccess in this directory turns pretty
 * URLs on where it is available; both forms hit this file.
 */

require __DIR__ . '/app/bootstrap.php';
// $config, $pdo, $auth, $repo, $view are defined by the bootstrap.

require VEC_APP . '/Controller.php';
require VEC_APP . '/controllers/HomeController.php';
require VEC_APP . '/controllers/AuthController.php';
require VEC_APP . '/controllers/AlumniController.php';
require VEC_APP . '/controllers/AdvisorController.php';
require VEC_APP . '/controllers/ExecController.php';
require VEC_APP . '/controllers/SchoolAdminController.php';
require VEC_APP . '/controllers/CentralAdminController.php';
require VEC_APP . '/controllers/MigrationController.php';
require VEC_APP . '/controllers/AccountController.php';
require VEC_APP . '/controllers/StudentController.php';

/**
 * route => array(ControllerClass, method)
 */
$routes = array(
    'home'                      => array('HomeController', 'index'),

    'login'                     => array('AuthController', 'login'),
    'logout'                    => array('AuthController', 'logout'),
    'register'                  => array('AuthController', 'register'),

    'account/profile'           => array('AccountController', 'profile'),
    'account/password'          => array('AccountController', 'password'),

    'student'                   => array('StudentController', 'form'),

    'alumni'                    => array('AlumniController', 'form'),
    'alumni/history'            => array('AlumniController', 'history'),

    'advisor'                   => array('AdvisorController', 'index'),
    'advisor/summary'           => array('AdvisorController', 'summary'),
    'advisor/fill'              => array('AdvisorController', 'fill'),

    'exec'                      => array('ExecController', 'dashboard'),
    'exec/departments'          => array('ExecController', 'departments'),
    'exec/years'                => array('ExecController', 'years'),
    'exec/export'               => array('ExecController', 'export'),

    'schooladmin'               => array('SchoolAdminController', 'users'),
    'schooladmin/user-create'   => array('SchoolAdminController', 'userCreate'),
    'schooladmin/user-status'   => array('SchoolAdminController', 'userStatus'),
    'schooladmin/departments'   => array('SchoolAdminController', 'departments'),
    'schooladmin/alumni'        => array('SchoolAdminController', 'alumni'),
    'schooladmin/alumni-state'  => array('SchoolAdminController', 'alumniState'),
    'schooladmin/import'        => array('SchoolAdminController', 'import'),

    'centraladmin'              => array('CentralAdminController', 'index'),
    'centraladmin/requests'     => array('CentralAdminController', 'requests'),
    'centraladmin/school-create' => array('CentralAdminController', 'schoolCreate'),
    'centraladmin/school-status' => array('CentralAdminController', 'schoolStatus'),
    'centraladmin/users'        => array('CentralAdminController', 'users'),
    'centraladmin/settings'     => array('CentralAdminController', 'settings'),
    'centraladmin/import-users' => array('CentralAdminController', 'importUsers'),

    'admin/migrations'          => array('MigrationController', 'index'),
);

$route = query('r', '');
if ($route === '') {
    // PATH_INFO is set when .htaccess rewriting is active.
    $pathInfo = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
    $route = $pathInfo !== '' ? $pathInfo : 'home';
}
$route = trim($route, '/');
if ($route === '') {
    $route = 'home';
}

/*
 * Stop before the database is queried when the code is newer than the schema.
 *
 * Deploying a release without running its migrations used to surface as a raw
 * SQL error on whatever page was hit first — including the public landing
 * page. The routes below stay open so an administrator can still sign in and
 * apply the migrations; everything else gets a plain "being updated" page.
 */
$migrationSafeRoutes = array('login', 'logout', 'admin/migrations');

if (!in_array($route, $migrationSafeRoutes, true)) {
    $schemaCheck = new Migrator($pdo, VEC_ROOT . '/migrations', $config['db']['prefix']);
    if ($schemaCheck->isOutdated()) {
        // The person who can fix this goes straight to the tool that fixes it.
        if ($auth->is('centraladmin')) {
            redirect('admin/migrations');
        }
        http_response_code(503);
        header('Retry-After: 3600');
        $view->render(
            'errors/migration-required',
            array('title' => 'ระบบกำลังปรับปรุง', 'route' => $route),
            'layout/blank'
        );
        exit;
    }
}

// Signed-in visitors landing on the marketing page go straight to their work.
if ($route === 'home' && $auth->check()) {
    redirect($auth->homeRoute());
}

if (!isset($routes[$route])) {
    http_response_code(404);
    $view->render('errors/404', array('route' => $route, 'title' => 'ไม่พบหน้าที่ต้องการ'),
        $auth->check() ? 'layout/app' : 'layout/public');
    exit;
}

list($class, $method) = $routes[$route];

try {
    $controller = new $class($auth, $repo, $view, $config, $route);
    $controller->$method();
} catch (PDOException $e) {
    app_log('DB error on ' . $route . ': ' . $e->getMessage());
    vec_fatal(
        'เกิดข้อผิดพลาดกับฐานข้อมูล',
        $e->getMessage(),
        !empty($config['app']['debug'])
    );
} catch (Exception $e) {
    app_log('Error on ' . $route . ': ' . $e->getMessage());
    vec_fatal(
        'เกิดข้อผิดพลาดในระบบ',
        $e->getMessage() . "\n" . $e->getTraceAsString(),
        !empty($config['app']['debug'])
    );
}
