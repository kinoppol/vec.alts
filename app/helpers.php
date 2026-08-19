<?php
/**
 * Shared functions. Kept procedural and dependency-free so install.php can
 * load them before anything else exists.
 */

/**
 * HTML-escape. Explicit flags because PHP 8.1 changed the defaults.
 * @param mixed $value
 * @return string
 */
function e($value)
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Read a value out of an array without notices on any PHP version.
 * @param array|null $array
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function arr($array, $key, $default = null)
{
    if (!is_array($array) || !array_key_exists($key, $array)) {
        return $default;
    }
    return $array[$key];
}

/**
 * Trimmed string from $_POST.
 * @return string
 */
function post($key, $default = '')
{
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return $default;
    }
    return trim((string) $_POST[$key]);
}

/**
 * Trimmed string from $_GET.
 * @return string
 */
function query($key, $default = '')
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return $default;
    }
    return trim((string) $_GET[$key]);
}

function post_int($key, $default = 0)
{
    $v = post($key, '');
    return $v === '' ? $default : (int) $v;
}

function query_int($key, $default = 0)
{
    $v = query($key, '');
    return $v === '' ? $default : (int) $v;
}

function post_array($key)
{
    if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
        return array();
    }
    return $_POST[$key];
}

function is_post()
{
    return isset($_SERVER['REQUEST_METHOD'])
        && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
}

/**
 * Base URL of the application, e.g. /vec.alts
 * @return string
 */
function base_url()
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($base === '.') {
        $base = '';
    }
    return $base;
}

/**
 * Build an application URL. Query-string routing keeps this working without
 * mod_rewrite, which is not guaranteed on the production CentOS box.
 * @param string $route
 * @param array $params
 * @return string
 */
function url($route = '', $params = array())
{
    $url = base_url() . '/index.php';
    $all = array();
    if ($route !== '') {
        $all['r'] = $route;
    }
    foreach ($params as $k => $v) {
        $all[$k] = $v;
    }
    if ($all) {
        $url .= '?' . http_build_query($all);
    }
    return $url;
}

function asset($path)
{
    return base_url() . '/assets/' . ltrim($path, '/');
}

/**
 * Redirect and stop.
 * @param string $to absolute or app-relative URL
 */
function redirect($to)
{
    if (strpos($to, 'http://') !== 0 && strpos($to, 'https://') !== 0 && strpos($to, '/') !== 0) {
        $to = url($to);
    }
    header('Location: ' . $to);
    exit;
}

/**
 * One-shot session message.
 * @param string $type success|error|info|warn
 * @param string $message
 */
function flash($type, $message)
{
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = array();
    }
    $_SESSION['_flash'][] = array('type' => $type, 'message' => $message);
}

/**
 * @return array
 */
function flash_take()
{
    $messages = isset($_SESSION['_flash']) && is_array($_SESSION['_flash'])
        ? $_SESSION['_flash']
        : array();
    $_SESSION['_flash'] = array();
    return $messages;
}

/**
 * Current CSRF token, generated on first use.
 * @return string
 */
function csrf_token()
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = vec_random_token(24);
    }
    return $_SESSION['_csrf'];
}

/**
 * Hidden input carrying the CSRF token.
 * @return string
 */
function csrf_field()
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * @return bool
 */
function csrf_check()
{
    $sent = isset($_POST['_token']) ? (string) $_POST['_token'] : '';
    $known = isset($_SESSION['_csrf']) ? (string) $_SESSION['_csrf'] : '';
    if ($sent === '' || $known === '') {
        return false;
    }
    return hash_equals($known, $sent);
}

/**
 * Abort the request unless the POST carried a valid token.
 */
function csrf_verify()
{
    if (!csrf_check()) {
        http_response_code(419);
        echo '<h1>คำขอหมดอายุ</h1><p>กรุณาย้อนกลับและส่งฟอร์มใหม่อีกครั้ง</p>';
        exit;
    }
}

/**
 * Thai Buddhist year for a Gregorian year.
 * @param int $year
 * @return int
 */
function to_thai_year($year)
{
    return ((int) $year) + 543;
}

/**
 * Current Thai academic year (rolls over in May).
 * @return int Buddhist year
 */
function current_academic_year()
{
    $month = (int) date('n');
    $year = (int) date('Y');
    if ($month < 5) {
        $year--;
    }
    return to_thai_year($year);
}

/**
 * Format an integer with thousands separators, tolerating null/empty.
 * @param mixed $number
 * @return string
 */
function num($number)
{
    if ($number === null || $number === '') {
        return '0';
    }
    return number_format((float) $number, 0, '.', ',');
}

/**
 * Percentage as a string with one decimal place.
 * @return string
 */
function pct($part, $whole)
{
    $whole = (float) $whole;
    if ($whole <= 0) {
        return '0.0';
    }
    return number_format(((float) $part / $whole) * 100, 1, '.', '');
}

/**
 * Thai date, e.g. 18 ส.ค. 2568
 * @param string|null $datetime
 * @return string
 */
function thai_date($datetime)
{
    if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    $months = array(
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
    );
    $m = (int) date('n', $ts);
    return (int) date('j', $ts) . ' ' . $months[$m] . ' ' . to_thai_year(date('Y', $ts))
        . ' ' . date('H:i', $ts);
}

/**
 * The six employment statuses tracked by the survey.
 * @return array code => array('label','icon','group')
 */
function employment_statuses()
{
    return array(
        'employed_match' => array('label' => 'มีงานทำ (ตรงสาขา)', 'icon' => '💼', 'group' => 'job'),
        'employed_other' => array('label' => 'มีงานทำ (ไม่ตรงสาขา)', 'icon' => '🧰', 'group' => 'job'),
        'freelance'      => array('label' => 'ประกอบอาชีพอิสระ', 'icon' => '🚀', 'group' => 'job'),
        'study'          => array('label' => 'ศึกษาต่อ', 'icon' => '🎓', 'group' => 'study'),
        'unemployed'     => array('label' => 'ว่างงาน / กำลังหางาน', 'icon' => '🔎', 'group' => 'note'),
        'military'       => array('label' => 'เกณฑ์ทหาร', 'icon' => '🪖', 'group' => 'note'),
    );
}

/**
 * @param string $code
 * @return string
 */
function employment_label($code)
{
    $all = employment_statuses();
    return isset($all[$code]) ? $all[$code]['label'] : 'ยังไม่ระบุ';
}

/**
 * Roles that can log in through the staff tab.
 * @return array
 */
function staff_roles()
{
    return array(
        'advisor'      => 'ครูที่ปรึกษา',
        'exec'         => 'ผู้บริหาร',
        'schooladmin'  => 'ผู้ดูแลสถานศึกษา',
        'centraladmin' => 'ผู้ดูแลระบบกลาง',
    );
}

/**
 * @param string $role
 * @return string
 */
function role_label($role)
{
    $roles = staff_roles();
    if ($role === 'alumni') {
        return 'ศิษย์เก่า';
    }
    return isset($roles[$role]) ? $roles[$role] : $role;
}

/**
 * Sidebar menu for a role. Every entry points at a route that exists.
 *
 * @param string $role
 * @return array of array('route','label')
 */
function app_menu($role)
{
    $menu = array();
    switch ($role) {
        case 'alumni':
            $menu = array(
                array('route' => 'alumni', 'label' => 'ข้อมูลของฉัน'),
                array('route' => 'alumni/history', 'label' => 'ประวัติการอัปเดต'),
            );
            break;
        case 'advisor':
            $menu = array(
                array('route' => 'advisor', 'label' => 'ศิษย์เก่าในความดูแล'),
                array('route' => 'advisor/summary', 'label' => 'สรุปกลุ่ม'),
            );
            break;
        case 'exec':
            $menu = array(
                array('route' => 'exec', 'label' => 'แดชบอร์ดภาพรวม'),
                array('route' => 'exec/departments', 'label' => 'รายงานตามแผนก'),
                array('route' => 'exec/years', 'label' => 'เปรียบเทียบปีการศึกษา'),
                array('route' => 'exec/export', 'label' => 'ส่งออกรายงาน'),
            );
            break;
        case 'schooladmin':
            $menu = array(
                array('route' => 'schooladmin', 'label' => 'ผู้ใช้งาน'),
                array('route' => 'schooladmin/alumni', 'label' => 'ข้อมูลศิษย์เก่า'),
                array('route' => 'schooladmin/import', 'label' => 'นำเข้าข้อมูล'),
                array('route' => 'schooladmin/departments', 'label' => 'จัดการสาขา'),
            );
            break;
        case 'centraladmin':
            $menu = array(
                array('route' => 'centraladmin', 'label' => 'สถานศึกษาทั้งหมด'),
                array('route' => 'centraladmin/requests', 'label' => 'คำขอสมัคร'),
                array('route' => 'centraladmin/users', 'label' => 'ผู้ใช้งานระบบ'),
                array('route' => 'admin/migrations', 'label' => 'Migration ฐานข้อมูล'),
                array('route' => 'centraladmin/settings', 'label' => 'ตั้งค่าระบบ'),
            );
            break;
    }

    // Appended rather than repeated in each branch: every role that signs in
    // with a password can change it. Alumni authenticate with their national
    // ID and have no password of their own to set.
    if ($menu && $role !== 'alumni') {
        $menu[] = array('route' => 'account/password', 'label' => 'เปลี่ยนรหัสผ่าน');
    }
    return $menu;
}

/**
 * Thai national ID checksum.
 * @param string $id
 * @return bool
 */
function valid_national_id($id)
{
    $id = preg_replace('/\D/', '', (string) $id);
    if (strlen($id) !== 13) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += ((int) $id[$i]) * (13 - $i);
    }
    $check = (11 - ($sum % 11)) % 10;
    return $check === (int) $id[12];
}

/**
 * Writes a line to storage/logs. Failure is ignored: logging must never take
 * the application down.
 * @param string $message
 */
function app_log($message)
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
}
