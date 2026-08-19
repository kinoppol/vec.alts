<?php
/**
 * Session authentication for the two account kinds:
 *  - staff  -> `users` table  (advisor / exec / schooladmin / centraladmin)
 *  - alumni -> `alumni` table (student code + national ID)
 */
class Auth
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $prefix;

    /** @var array|null cached current identity */
    private $current = null;

    public function __construct(PDO $db, $prefix)
    {
        $this->db = $db;
        $this->prefix = (string) $prefix;
    }

    private function t($name)
    {
        return $this->prefix . $name;
    }

    /**
     * @param string $identifier email or username
     * @param string $password
     * @return array array('ok'=>bool, 'error'=>string, 'user'=>array|null)
     */
    public function loginStaff($identifier, $password)
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '' || $password === '') {
            return array('ok' => false, 'error' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 'user' => null);
        }

        $sql = 'SELECT u.*, s.name AS school_name, s.status AS school_status'
            . ' FROM `' . $this->t('users') . '` u'
            . ' LEFT JOIN `' . $this->t('schools') . '` s ON s.id = u.school_id'
            . ' WHERE u.email = ? OR u.username = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($identifier, $identifier));
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordFailure('staff', $identifier);
            return array('ok' => false, 'error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 'user' => null);
        }
        if ($user['status'] === 'pending') {
            return array('ok' => false, 'error' => 'บัญชีนี้ยังรอการอนุมัติจากผู้ดูแล', 'user' => null);
        }
        if ($user['status'] !== 'active') {
            return array('ok' => false, 'error' => 'บัญชีนี้ถูกระงับการใช้งาน', 'user' => null);
        }
        if ($user['role'] !== 'centraladmin' && $user['school_status'] === 'pending') {
            return array('ok' => false, 'error' => 'สถานศึกษาของคุณยังรอการอนุมัติ', 'user' => null);
        }
        if ($user['role'] !== 'centraladmin' && $user['school_status'] === 'suspended') {
            return array('ok' => false, 'error' => 'สถานศึกษาของคุณถูกระงับการใช้งาน', 'user' => null);
        }

        // Upgrade the stored hash if the cost changed between environments.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT, array('cost' => 10))) {
            $new = password_hash($password, PASSWORD_DEFAULT, array('cost' => 10));
            if ($new !== false) {
                $upd = $this->db->prepare(
                    'UPDATE `' . $this->t('users') . '` SET password_hash = ? WHERE id = ?'
                );
                $upd->execute(array($new, $user['id']));
            }
        }

        $this->startSession(array(
            'kind'        => 'staff',
            'id'          => (int) $user['id'],
            'school_id'   => $user['school_id'] === null ? null : (int) $user['school_id'],
            'role'        => $user['role'],
            'name'        => $user['full_name'],
            'school_name' => $user['school_name'],
        ));

        $upd = $this->db->prepare(
            'UPDATE `' . $this->t('users') . '` SET last_login_at = ? WHERE id = ?'
        );
        $upd->execute(array(date('Y-m-d H:i:s'), $user['id']));

        return array('ok' => true, 'error' => '', 'user' => $user);
    }

    /**
     * Alumni sign in with their old student code and their national ID.
     *
     * @param string $studentCode
     * @param string $nationalId
     * @return array
     */
    public function loginAlumni($studentCode, $nationalId)
    {
        $studentCode = trim((string) $studentCode);
        $nationalId = preg_replace('/\D/', '', (string) $nationalId);

        if ($studentCode === '' || $nationalId === '') {
            return array('ok' => false, 'error' => 'กรุณากรอกรหัสนักศึกษาและเลขบัตรประชาชน', 'user' => null);
        }

        $sql = 'SELECT a.*, s.name AS school_name, s.status AS school_status,'
            . ' d.name AS department_name'
            . ' FROM `' . $this->t('alumni') . '` a'
            . ' LEFT JOIN `' . $this->t('schools') . '` s ON s.id = a.school_id'
            . ' LEFT JOIN `' . $this->t('departments') . '` d ON d.id = a.department_id'
            . ' WHERE a.student_code = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($studentCode));
        $alumni = $stmt->fetch();

        if (!$alumni || !password_verify($nationalId, $alumni['national_id_hash'])) {
            $this->recordFailure('alumni', $studentCode);
            return array('ok' => false, 'error' => 'รหัสนักศึกษาหรือเลขบัตรประชาชนไม่ถูกต้อง', 'user' => null);
        }
        if ($alumni['status'] === 'inactive') {
            return array('ok' => false, 'error' => 'บัญชีนี้ถูกระงับการใช้งาน', 'user' => null);
        }
        if ($alumni['school_status'] !== 'active') {
            return array('ok' => false, 'error' => 'สถานศึกษาของคุณยังไม่เปิดใช้งานระบบ', 'user' => null);
        }

        // Students and graduates are the same people in the same table and
        // sign in identically; which screen they land on is decided by where
        // they are in their studies. arr() covers the window between deploying
        // this code and running the migration that adds the column.
        $studying = arr($alumni, 'study_state', 'graduated') === 'studying';

        $this->startSession(array(
            'kind'        => 'alumni',
            'id'          => (int) $alumni['id'],
            'school_id'   => (int) $alumni['school_id'],
            'role'        => $studying ? 'student' : 'alumni',
            'name'        => trim($alumni['title'] . $alumni['first_name'] . ' ' . $alumni['last_name']),
            'school_name' => $alumni['school_name'],
        ));

        $upd = $this->db->prepare(
            'UPDATE `' . $this->t('alumni') . '` SET last_login_at = ? WHERE id = ?'
        );
        $upd->execute(array(date('Y-m-d H:i:s'), $alumni['id']));

        return array('ok' => true, 'error' => '', 'user' => $alumni);
    }

    /**
     * @param array $identity
     */
    private function startSession($identity)
    {
        // New session ID on privilege change, to blunt fixation attacks.
        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
        $identity['login_at'] = time();
        $_SESSION['auth'] = $identity;
        $this->current = $identity;
        $_SESSION['_csrf'] = vec_random_token(24);
    }

    public function logout()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        $this->current = null;
    }

    /**
     * @return array|null
     */
    public function user()
    {
        if ($this->current !== null) {
            return $this->current;
        }
        if (isset($_SESSION['auth']) && is_array($_SESSION['auth'])) {
            $this->current = $_SESSION['auth'];
            return $this->current;
        }
        return null;
    }

    public function check()
    {
        return $this->user() !== null;
    }

    /**
     * Merges changed fields into the signed-in identity.
     *
     * The session carries a copy of the account row taken at sign-in, so an
     * account that edits itself would otherwise keep stamping its old name on
     * audit entries until the next login.
     *
     * @param array $changes
     */
    public function updateIdentity($changes)
    {
        $identity = $this->user();
        if ($identity === null) {
            return;
        }
        foreach ($changes as $key => $value) {
            $identity[$key] = $value;
        }
        $_SESSION['auth'] = $identity;
        $this->current = $identity;
    }

    /**
     * @return string '' when signed out
     */
    public function role()
    {
        $u = $this->user();
        return $u ? $u['role'] : '';
    }

    /**
     * @return int 0 when signed out
     */
    public function id()
    {
        $u = $this->user();
        return $u ? (int) $u['id'] : 0;
    }

    /**
     * @return int|null
     */
    public function schoolId()
    {
        $u = $this->user();
        if (!$u || !isset($u['school_id'])) {
            return null;
        }
        return $u['school_id'] === null ? null : (int) $u['school_id'];
    }

    /**
     * @param string|array $roles
     * @return bool
     */
    public function is($roles)
    {
        $role = $this->role();
        if ($role === '') {
            return false;
        }
        if (is_array($roles)) {
            return in_array($role, $roles, true);
        }
        return $role === $roles;
    }

    /**
     * Sends the visitor to the login screen unless they hold one of the roles.
     * @param string|array $roles
     */
    public function require_role($roles)
    {
        if (!$this->check()) {
            flash('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
            redirect('login');
        }
        if (!$this->is($roles)) {
            http_response_code(403);
            flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
            redirect($this->homeRoute());
        }
    }

    /**
     * Landing route for the signed-in role.
     * @return string
     */
    public function homeRoute()
    {
        switch ($this->role()) {
            case 'student':
                return 'student';
            case 'alumni':
                return 'alumni';
            case 'advisor':
                return 'advisor';
            case 'exec':
                return 'exec';
            case 'schooladmin':
                return 'schooladmin';
            case 'centraladmin':
                return 'centraladmin';
        }
        return 'home';
    }

    private function recordFailure($kind, $identifier)
    {
        app_log('login failed (' . $kind . '): ' . $identifier
            . ' from ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
        // Small delay blunts trivial online guessing without needing a store.
        usleep(250000);
    }
}
