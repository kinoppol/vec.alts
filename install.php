<?php
/**
 * Installer / maintenance console.
 *
 * Designed to be run more than once:
 *  - First run walks through requirements, database settings and the central
 *    administrator account.
 *  - Later runs require the central administrator's password and then offer
 *    the maintenance actions: update the connection settings, apply pending
 *    migrations, reset a password, load demo data, or wipe and rebuild.
 *
 * Runs on PHP 5.4 and PHP 8 alike; no syntax newer than 5.4 is used.
 */

define('VEC_INSTALLER', true);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/app/compat.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/config_io.php';
require __DIR__ . '/app/Database.php';
require __DIR__ . '/app/Schema.php';
require __DIR__ . '/app/Migrator.php';
require __DIR__ . '/app/Repository.php';
require __DIR__ . '/app/Seeder.php';

if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Bangkok');
}
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

vec_start_session(array('name' => 'VECINSTALL', 'lifetime' => 3600));

$rawConfig = vec_load_config();
$config = vec_normalise_config($rawConfig === null ? array() : $rawConfig);
$isInstalled = ($rawConfig !== null && !empty($rawConfig['installed']));

/** Messages queued for the current render. */
$notices = array();

/**
 * @param string $type success|error|warn|info
 * @param string $message
 */
function notice($type, $message)
{
    global $notices;
    $notices[] = array('type' => $type, 'message' => $message);
}

/**
 * @return string
 */
function install_url($params = array())
{
    $script = basename(__FILE__);
    return $params ? $script . '?' . http_build_query($params) : $script;
}

// =========================================================================
//  Environment checks
// =========================================================================

/**
 * @return array each entry: label, ok, required, detail
 */
function requirement_checks()
{
    $checks = array();

    $phpOk = version_compare(PHP_VERSION, '5.4.0', '>=');
    $checks[] = array(
        'label'    => 'PHP 5.4 ขึ้นไป',
        'ok'       => $phpOk,
        'required' => true,
        'detail'   => 'ตรวจพบ ' . PHP_VERSION,
    );

    foreach (array('pdo' => 'PDO', 'pdo_mysql' => 'PDO MySQL', 'session' => 'Session', 'json' => 'JSON') as $ext => $label) {
        $checks[] = array(
            'label'    => 'ส่วนขยาย ' . $label,
            'ok'       => extension_loaded($ext),
            'required' => true,
            'detail'   => extension_loaded($ext) ? 'พร้อมใช้งาน' : 'ไม่พบส่วนขยายนี้',
        );
    }

    foreach (array('mbstring' => 'mbstring', 'openssl' => 'OpenSSL') as $ext => $label) {
        $loaded = extension_loaded($ext);
        $checks[] = array(
            'label'    => 'ส่วนขยาย ' . $label . ' (แนะนำ)',
            'ok'       => $loaded,
            'required' => false,
            'detail'   => $loaded ? 'พร้อมใช้งาน' : 'ไม่พบ — ระบบจะใช้ทางเลือกสำรองแทน',
        );
    }

    $configDir = __DIR__ . '/config';
    $configWritable = is_dir($configDir) ? is_writable($configDir) : is_writable(__DIR__);
    $checks[] = array(
        'label'    => 'เขียนไฟล์ในโฟลเดอร์ config/ ได้',
        'ok'       => $configWritable,
        'required' => true,
        'detail'   => $configWritable ? 'เขียนได้' : 'ต้องกำหนดสิทธิ์ให้เว็บเซิร์ฟเวอร์เขียนได้ (เช่น chmod 775)',
    );

    $storageDir = __DIR__ . '/storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir . '/logs', 0775, true);
    }
    $storageWritable = is_dir($storageDir) && is_writable($storageDir);
    $checks[] = array(
        'label'    => 'เขียนไฟล์ในโฟลเดอร์ storage/ ได้',
        'ok'       => $storageWritable,
        'required' => false,
        'detail'   => $storageWritable ? 'เขียนได้' : 'บันทึก log ไม่ได้ แต่ระบบยังทำงานต่อได้',
    );

    return $checks;
}

/**
 * @param array $checks
 * @return bool
 */
function requirements_pass($checks)
{
    foreach ($checks as $check) {
        if ($check['required'] && !$check['ok']) {
            return false;
        }
    }
    return true;
}

// =========================================================================
//  Database helpers
// =========================================================================

/**
 * Connects with the given settings, optionally creating the database.
 *
 * @param array $db
 * @param bool $createIfMissing
 * @return array array('ok' => bool, 'pdo' => PDO|null, 'error' => string, 'created' => bool)
 */
function try_connect($db, $createIfMissing = false)
{
    $out = array('ok' => false, 'pdo' => null, 'error' => '', 'created' => false);

    try {
        $out['pdo'] = Database::connect($db, true);
        $out['ok'] = true;
        return $out;
    } catch (PDOException $e) {
        $firstError = $e->getMessage();
    }

    if (!$createIfMissing) {
        $out['error'] = $firstError;
        return $out;
    }

    // Connect without selecting a database, then create it.
    try {
        $server = Database::connect($db, false);
        $caps = Database::capabilities($server);
        $server->exec(
            'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['name']) . '`'
            . ' DEFAULT CHARACTER SET ' . $caps['charset']
            . ' COLLATE ' . $caps['collation']
        );
        $out['pdo'] = Database::connect($db, true);
        $out['ok'] = true;
        $out['created'] = true;
        return $out;
    } catch (PDOException $e) {
        $out['error'] = $firstError . ' / สร้างฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage();
        return $out;
    }
}

/**
 * Verifies a central administrator's credentials against an existing install.
 *
 * @return array array('ok' => bool, 'error' => string)
 */
function verify_central_admin($config, $email, $password)
{
    $result = try_connect($config['db'], false);
    if (!$result['ok']) {
        return array('ok' => false, 'error' => 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . $result['error']);
    }

    $prefix = $config['db']['prefix'];
    try {
        $stmt = $result['pdo']->prepare(
            'SELECT * FROM `' . $prefix . 'users` WHERE email = ? AND role = ? LIMIT 1'
        );
        $stmt->execute(array($email, 'centraladmin'));
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        return array('ok' => false, 'error' => 'อ่านตารางผู้ใช้ไม่ได้: ' . $e->getMessage());
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        usleep(400000);
        return array('ok' => false, 'error' => 'อีเมลหรือรหัสผ่านผู้ดูแลระบบกลางไม่ถูกต้อง');
    }
    return array('ok' => true, 'error' => '');
}

/**
 * The escape hatch for a site whose database credentials are wrong: an empty
 * file at config/install.unlock lets the installer in without a login.
 * @return bool
 */
function unlock_file_present()
{
    return is_file(__DIR__ . '/config/install.unlock');
}

/**
 * @return bool
 */
function installer_authorised($isInstalled)
{
    if (!$isInstalled) {
        return true;
    }
    if (!empty($_SESSION['install_auth'])) {
        return true;
    }
    return unlock_file_present();
}

// =========================================================================
//  Actions
// =========================================================================

$step = isset($_GET['step']) ? (string) $_GET['step'] : '';
$authorised = installer_authorised($isInstalled);

// ------------------------------------------------------------- sign in / out
if (is_post() && post('action') === 'unlock') {
    csrf_verify();
    $check = verify_central_admin($config, post('email'), post('password'));
    if ($check['ok']) {
        $_SESSION['install_auth'] = true;
        $authorised = true;
        notice('success', 'ยืนยันตัวตนเรียบร้อย');
        $step = 'manage';
    } else {
        notice('error', $check['error']);
    }
}

if (isset($_GET['signout'])) {
    unset($_SESSION['install_auth']);
    header('Location: ' . install_url());
    exit;
}

// --------------------------------------------------------------- save config
if (is_post() && post('action') === 'save-database' && $authorised) {
    csrf_verify();

    $db = array(
        'host'   => post('db_host', 'localhost'),
        'port'   => post_int('db_port', 3306),
        'name'   => post('db_name'),
        'user'   => post('db_user'),
        'pass'   => isset($_POST['db_pass']) ? (string) $_POST['db_pass'] : '',
        'socket' => post('db_socket'),
        'prefix' => preg_replace('/[^A-Za-z0-9_]/', '', post('db_prefix', 'va_')),
    );

    if ($db['name'] === '') {
        notice('error', 'กรุณาระบุชื่อฐานข้อมูล');
        $_SESSION['install_db'] = $db;
        $step = 'database';
    } else {
        $result = try_connect($db, post('db_create') === '1');
        if (!$result['ok']) {
            notice('error', 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $result['error']);
            $_SESSION['install_db'] = $db;
            $step = 'database';
        } else {
            if ($result['created']) {
                notice('success', 'สร้างฐานข้อมูล ' . $db['name'] . ' ให้เรียบร้อยแล้ว');
            }
            $_SESSION['install_db'] = $db;

            $config['db'] = $db;
            $config['app']['name'] = post('app_name', $config['app']['name']);
            $config['app']['env'] = post('app_env', 'production');
            $config['app']['debug'] = post('app_env') === 'development';
            $config['app']['timezone'] = post('app_timezone', 'Asia/Bangkok');

            $write = vec_write_config($config);
            if (!$write['ok']) {
                notice('error', 'บันทึกไฟล์ตั้งค่าไม่สำเร็จ: ' . $write['error']);
                $step = 'database';
            } else {
                $caps = Database::capabilities($result['pdo']);
                notice('success', 'เชื่อมต่อ ' . $caps['flavour'] . ' ' . $caps['version']
                    . ' และบันทึกการตั้งค่าเรียบร้อยแล้ว');

                // Applying the schema straight away means the admin step has
                // somewhere to write the account to.
                $migrator = new Migrator($result['pdo'], __DIR__ . '/migrations', $db['prefix']);
                $migration = $migrator->migrate();
                if ($migration['failed'] !== null) {
                    notice('error', 'สร้างโครงสร้างฐานข้อมูลไม่สำเร็จที่ '
                        . $migration['failed']['version'] . ': ' . $migration['failed']['error']);
                    $step = 'database';
                } else {
                    $applied = count($migration['applied']);
                    notice('success', $applied > 0
                        ? 'ปรับปรุงโครงสร้างฐานข้อมูลแล้ว ' . $applied . ' รายการ'
                        : 'โครงสร้างฐานข้อมูลเป็นปัจจุบันอยู่แล้ว');
                    $step = $isInstalled ? 'manage' : 'admin';
                }
            }
        }
    }
}

// ------------------------------------------------------- create central admin
if (is_post() && post('action') === 'save-admin' && $authorised) {
    csrf_verify();

    $email = post('admin_email');
    $password = post('admin_password');
    $confirm = post('admin_password_confirm');
    $name = post('admin_name');

    $errors = array();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
    }
    if ($password !== $confirm) {
        $errors[] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    }
    if ($name === '') {
        $errors[] = 'กรุณากรอกชื่อผู้ดูแลระบบ';
    }

    if ($errors) {
        foreach ($errors as $message) {
            notice('error', $message);
        }
        $step = 'admin';
    } else {
        $result = try_connect($config['db'], false);
        if (!$result['ok']) {
            notice('error', 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . $result['error']);
            $step = 'database';
        } else {
            $repo = new Repository($result['pdo'], $config['db']['prefix']);
            $existing = $repo->userByEmail($email);

            try {
                if ($existing !== null) {
                    // Re-running the installer with the same address resets
                    // the password rather than failing on the unique index.
                    $repo->setUserPassword($existing['id'], $password);
                    $repo->run(
                        'UPDATE `{p}users` SET role = ?, full_name = ?, status = ?, school_id = NULL'
                        . ' WHERE id = ?',
                        array('centraladmin', $name, 'active', $existing['id'])
                    );
                    notice('success', 'อัปเดตบัญชีผู้ดูแลระบบกลางที่มีอยู่เดิมเรียบร้อยแล้ว');
                } else {
                    $repo->createUser(array(
                        'school_id' => null,
                        'role'      => 'centraladmin',
                        'email'     => $email,
                        'password'  => $password,
                        'full_name' => $name,
                        'status'    => 'active',
                    ));
                    notice('success', 'สร้างบัญชีผู้ดูแลระบบกลางเรียบร้อยแล้ว');
                }

                $config['installed'] = true;
                $config['installed_at'] = date('Y-m-d H:i:s');
                $config['version'] = VEC_VERSION;
                $write = vec_write_config($config);
                if (!$write['ok']) {
                    notice('error', 'บันทึกไฟล์ตั้งค่าไม่สำเร็จ: ' . $write['error']);
                } else {
                    $isInstalled = true;
                    $_SESSION['install_auth'] = true;
                    $_SESSION['install_admin_email'] = $email;
                    $step = 'done';
                }
            } catch (PDOException $e) {
                notice('error', 'สร้างบัญชีไม่สำเร็จ: ' . $e->getMessage());
                $step = 'admin';
            }
        }
    }
}

// -------------------------------------------------------- maintenance actions
if (is_post() && post('action') === 'maintenance' && $authorised) {
    csrf_verify();

    $task = post('task');
    $result = try_connect($config['db'], false);

    if (!$result['ok']) {
        notice('error', 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . $result['error']);
    } else {
        $pdo = $result['pdo'];
        $prefix = $config['db']['prefix'];
        $migrator = new Migrator($pdo, __DIR__ . '/migrations', $prefix);
        $repo = new Repository($pdo, $prefix);

        if ($task === 'migrate') {
            $run = $migrator->migrate();
            if ($run['failed'] !== null) {
                notice('error', 'migration ' . $run['failed']['version'] . ' ล้มเหลว: '
                    . $run['failed']['error']);
            }
            $count = count($run['applied']);
            notice($count > 0 ? 'success' : 'info', $count > 0
                ? 'ปรับปรุงโครงสร้างฐานข้อมูลแล้ว ' . $count . ' รายการ'
                : 'โครงสร้างฐานข้อมูลเป็นปัจจุบันอยู่แล้ว');

        } elseif ($task === 'rollback') {
            $run = $migrator->rollback();
            if ($run['failed'] !== null) {
                notice('error', 'ย้อนกลับล้มเหลว: ' . $run['failed']['error']);
            }
            $count = count($run['rolled_back']);
            notice($count > 0 ? 'success' : 'info', $count > 0
                ? 'ย้อนกลับ migration แล้ว ' . $count . ' รายการ'
                : 'ไม่มี migration ที่ย้อนกลับได้');

        } elseif ($task === 'seed') {
            try {
                $seeder = new Seeder($repo);
                $seed = $seeder->run();
                notice($seed['ok'] ? 'success' : 'warn', $seed['message']);
                if (!empty($seed['accounts'])) {
                    notice('info', 'บัญชีตัวอย่าง — ' . implode(' · ', $seed['accounts']));
                }
            } catch (PDOException $e) {
                notice('error', 'สร้างข้อมูลตัวอย่างไม่สำเร็จ: ' . $e->getMessage());
            }

        } elseif ($task === 'reset-admin') {
            $email = post('reset_email');
            $password = post('reset_password');
            if (mb_strlen($password) < 8) {
                notice('error', 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
            } else {
                $user = $repo->userByEmail($email);
                if ($user === null) {
                    notice('error', 'ไม่พบบัญชีอีเมลนี้ในระบบ');
                } else {
                    $repo->setUserPassword($user['id'], $password);
                    $repo->run(
                        'UPDATE `{p}users` SET status = ? WHERE id = ?',
                        array('active', $user['id'])
                    );
                    notice('success', 'ตั้งรหัสผ่านใหม่ให้ ' . $email . ' เรียบร้อยแล้ว');
                }
            }

        } elseif ($task === 'reinstall') {
            if (post('confirm') !== 'REINSTALL') {
                notice('error', 'ยังไม่ได้พิมพ์คำยืนยัน REINSTALL จึงไม่ได้ดำเนินการใด ๆ');
            } else {
                try {
                    $dropped = $migrator->dropAllTables();
                    $run = $migrator->migrate();
                    if ($run['failed'] !== null) {
                        notice('error', 'สร้างโครงสร้างใหม่ไม่สำเร็จ: ' . $run['failed']['error']);
                    } else {
                        notice('success', 'ลบตารางเดิม ' . count($dropped) . ' ตาราง'
                            . ' และสร้างโครงสร้างใหม่ ' . count($run['applied']) . ' รายการเรียบร้อยแล้ว');
                        notice('warn', 'ข้อมูลเดิมทั้งหมดถูกลบ รวมถึงบัญชีผู้ดูแลระบบกลาง'
                            . ' — กรุณาสร้างบัญชีใหม่ในขั้นตอนถัดไป');
                        $config['installed'] = false;
                        vec_write_config($config);
                        $isInstalled = false;
                        $step = 'admin';
                    }
                } catch (PDOException $e) {
                    notice('error', 'ล้างฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage());
                }
            }
        } else {
            notice('error', 'คำสั่งไม่ถูกต้อง');
        }
    }
}

// =========================================================================
//  Decide which screen to show
// =========================================================================

$checks = requirement_checks();
$checksPass = requirements_pass($checks);

if ($step === '') {
    if ($isInstalled && !$authorised) {
        $step = 'unlock';
    } elseif ($isInstalled) {
        $step = 'manage';
    } else {
        $step = 'requirements';
    }
}
if ($isInstalled && !$authorised && $step !== 'unlock') {
    $step = 'unlock';
}
if (!$isInstalled && !$checksPass && $step !== 'requirements') {
    $step = 'requirements';
    notice('error', 'ยังมีข้อกำหนดที่ไม่ผ่าน กรุณาแก้ไขก่อนดำเนินการต่อ');
}

// Current DB values for the form: session first, then the saved config.
$dbForm = isset($_SESSION['install_db']) ? $_SESSION['install_db'] : $config['db'];

// Migration status, when a connection is possible.
$migrationRows = array();
$pendingCount = 0;
$dbInfo = null;
if ($step === 'manage' || $step === 'done') {
    $probe = try_connect($config['db'], false);
    if ($probe['ok']) {
        $migrator = new Migrator($probe['pdo'], __DIR__ . '/migrations', $config['db']['prefix']);
        $migrationRows = $migrator->status();
        foreach ($migrationRows as $row) {
            if ($row['state'] === 'pending') {
                $pendingCount++;
            }
        }
        $caps = Database::capabilities($probe['pdo']);
        $dbInfo = array(
            'flavour'   => $caps['flavour'],
            'version'   => $caps['version_full'],
            'charset'   => $caps['charset'],
            'collation' => $caps['collation'],
            'batch'     => $migrator->currentBatch(),
        );
    } else {
        notice('error', 'เชื่อมต่อฐานข้อมูลด้วยค่าที่บันทึกไว้ไม่ได้: ' . $probe['error']);
    }
}

require __DIR__ . '/views/install/page.php';
