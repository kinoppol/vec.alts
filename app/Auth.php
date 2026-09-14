<?php
/**
 * Session authentication for the two account kinds:
 *  - staff  -> `users` table  (advisor / exec / schooladmin / centraladmin)
 *  - alumni -> `alumni` table (student code + password, or on first use the
 *              national ID plus a one-time code from their teacher)
 */
class Auth
{
    /** Wrong attempts on one student code before it is locked. */
    const ALUMNI_MAX_FAILURES = 5;

    /** How long a locked student code stays locked. */
    const ALUMNI_LOCK_MINUTES = 15;

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
     * Everyday sign-in for students and graduates: student code and the
     * password they chose.
     *
     * While the central administrator still has the one-time code switched
     * off, someone who has never set a password may type their national ID
     * here instead. That session is not asked to set a password: letting it
     * would let whoever bought the ID choose the password before the owner.
     *
     * @param string $studentCode
     * @param string $password
     * @return array
     */
    public function loginAlumni($studentCode, $password)
    {
        $studentCode = trim((string) $studentCode);
        $password = (string) $password;

        if ($studentCode === '' || $password === '') {
            return array('ok' => false, 'error' => 'กรุณากรอกรหัสนักศึกษาและรหัสผ่าน', 'user' => null);
        }

        $legacyAllowed = !$this->accessCodeRequired();
        $legacyId = preg_replace('/\D/', '', $password);
        $now = date('Y-m-d H:i:s');
        $locked = false;
        $alumni = null;

        // Student codes are only unique within a school, so the same code can
        // belong to people at different institutions. The secret decides
        // which of them is signing in.
        foreach ($this->alumniByCode($studentCode) as $row) {
            if ($this->isLocked($row, $now)) {
                $locked = true;
                continue;
            }
            $hash = (string) arr($row, 'password_hash', '');
            if ($hash !== '') {
                if (password_verify($password, $hash)) {
                    $alumni = $row;
                    break;
                }
            } elseif ($legacyAllowed && $legacyId !== ''
                && password_verify($legacyId, $row['national_id_hash'])) {
                $alumni = $row;
                break;
            }
        }

        if (!$alumni) {
            return $this->alumniFailure($studentCode, $locked,
                'รหัสนักศึกษาหรือรหัสผ่านไม่ถูกต้อง');
        }

        return $this->finishAlumniLogin($alumni, false);
    }

    /**
     * First use, or a forgotten password: national ID plus the one-time code
     * a teacher issued. Succeeding opens a session that can do nothing until a
     * password has been chosen.
     *
     * @param string $studentCode
     * @param string $nationalId
     * @param string $accessCode
     * @return array
     */
    public function loginAlumniFirstTime($studentCode, $nationalId, $accessCode)
    {
        $studentCode = trim((string) $studentCode);
        $nationalId = preg_replace('/\D/', '', (string) $nationalId);
        $accessCode = self::normaliseAccessCode($accessCode);

        if ($studentCode === '' || $nationalId === '' || $accessCode === '') {
            return array('ok' => false,
                'error' => 'กรุณากรอกรหัสนักศึกษา เลขบัตรประชาชน และรหัสเข้าใช้ครั้งแรก', 'user' => null);
        }

        $now = date('Y-m-d H:i:s');
        $locked = false;
        $alumni = null;

        foreach ($this->alumniByCode($studentCode) as $row) {
            if ($this->isLocked($row, $now)) {
                $locked = true;
                continue;
            }
            if (!password_verify($nationalId, $row['national_id_hash'])) {
                continue;
            }
            $codeHash = (string) arr($row, 'access_code_hash', '');
            $expires = (string) arr($row, 'access_code_expires_at', '');
            if ($codeHash !== '' && $expires !== '' && $expires >= $now
                && password_verify($accessCode, $codeHash)) {
                $alumni = $row;
            }
            break;
        }

        if (!$alumni) {
            // One message for every cause. Saying "the code has expired" would
            // confirm to someone holding a leaked ID that the ID was right.
            return $this->alumniFailure($studentCode, $locked,
                'ข้อมูลไม่ถูกต้อง หรือรหัสเข้าใช้ครั้งแรกหมดอายุแล้ว กรุณาติดต่อครูที่ปรึกษาเพื่อขอรหัสใหม่');
        }

        return $this->finishAlumniLogin($alumni, true);
    }

    /**
     * @param string $code
     * @return string upper case, spaces and dashes removed
     */
    public static function normaliseAccessCode($code)
    {
        return strtoupper(preg_replace('/[\s\-]/', '', (string) $code));
    }

    /**
     * Why a proposed password is unacceptable, or '' when it is fine.
     *
     * Deliberately no rules about symbols or mixed case: people who are made
     * to invent those write them on paper. What is refused is what an attacker
     * holding this person's leaked records would try first.
     *
     * @param string $password
     * @param array $alumni the person's row
     * @return string
     */
    public static function alumniPasswordProblem($password, $alumni)
    {
        $password = (string) $password;
        if (mb_strlen($password) < 8) {
            return 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
        }
        // Separators are ignored, so 1-2345-67890-12-3 is refused as well.
        $digits = preg_replace('/[\s\-]/', '', $password);
        $allDigits = ctype_digit($digits);
        if ($allDigits && password_verify($digits, (string) arr($alumni, 'national_id_hash', ''))) {
            return 'ห้ามใช้เลขบัตรประชาชนเป็นรหัสผ่าน';
        }
        if ($digits === (string) arr($alumni, 'student_code', '')) {
            return 'ห้ามใช้รหัสนักศึกษาเป็นรหัสผ่าน';
        }
        $phone = preg_replace('/\D/', '', (string) arr($alumni, 'phone', ''));
        if ($allDigits && $phone !== '' && $digits === $phone) {
            return 'ห้ามใช้เบอร์โทรศัพท์เป็นรหัสผ่าน';
        }
        $common = array('12345678', '123456789', '1234567890', '87654321', '11111111',
            '00000000', '88888888', 'password', 'password1', 'qwertyui', 'abcd1234', '1q2w3e4r');
        if (in_array(strtolower($password), $common, true)) {
            return 'รหัสผ่านนี้เดาง่ายเกินไป กรุณาตั้งรหัสอื่น';
        }
        return '';
    }

    /**
     * @return array rows sharing the student code, with school and department
     */
    private function alumniByCode($studentCode)
    {
        $sql = 'SELECT a.*, s.name AS school_name, s.status AS school_status,'
            . ' d.name AS department_name'
            . ' FROM `' . $this->t('alumni') . '` a'
            . ' LEFT JOIN `' . $this->t('schools') . '` s ON s.id = a.school_id'
            . ' LEFT JOIN `' . $this->t('departments') . '` d ON d.id = a.department_id'
            . ' WHERE a.student_code = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($studentCode));
        return $stmt->fetchAll();
    }

    private function isLocked($row, $now)
    {
        $until = (string) arr($row, 'locked_until', '');
        return $until !== '' && $until > $now;
    }

    /**
     * Counts a failed attempt against the student code and says so.
     */
    private function alumniFailure($studentCode, $locked, $message)
    {
        $this->recordFailure('alumni', $studentCode);
        $this->countAlumniFailure($studentCode);
        if ($locked) {
            $message = 'บัญชีนี้ถูกล็อกชั่วคราวเพราะกรอกข้อมูลผิดหลายครั้ง กรุณารอ '
                . self::ALUMNI_LOCK_MINUTES . ' นาทีแล้วลองใหม่';
        }
        return array('ok' => false, 'error' => $message, 'user' => null);
    }

    /**
     * Adds one failure to every unlocked row with this code, locking the ones
     * that reach the limit.
     *
     * Every row, because an attacker does not say which school they mean. The
     * counter restarts on lock, so the first mistake after a lock expires does
     * not lock the account again straight away.
     */
    private function countAlumniFailure($studentCode)
    {
        $now = date('Y-m-d H:i:s');
        $until = date('Y-m-d H:i:s', time() + self::ALUMNI_LOCK_MINUTES * 60);
        $limit = self::ALUMNI_MAX_FAILURES;

        // MySQL assigns left to right, so locked_until is decided from the
        // counter's old value before the counter itself changes.
        $sql = 'UPDATE `' . $this->t('alumni') . '` SET'
            . ' locked_until = CASE WHEN login_failures + 1 >= ? THEN ? ELSE locked_until END,'
            . ' login_failures = CASE WHEN login_failures + 1 >= ? THEN 0 ELSE login_failures + 1 END'
            . ' WHERE student_code = ? AND (locked_until IS NULL OR locked_until <= ?)';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array($limit, $until, $limit, $studentCode, $now));
        } catch (PDOException $e) {
            // Before migration 0011 the columns do not exist yet; sign-in must
            // still work so an administrator can reach the migration screen.
            app_log('alumni lockout unavailable: ' . $e->getMessage());
        }
    }

    /**
     * @param array $alumni
     * @param bool $mustSetPassword
     * @return array
     */
    private function finishAlumniLogin($alumni, $mustSetPassword)
    {
        if ($alumni['status'] === 'inactive') {
            return array('ok' => false, 'error' => 'บัญชีนี้ถูกระงับการใช้งาน', 'user' => null);
        }
        if ($alumni['school_status'] !== 'active') {
            return array('ok' => false, 'error' => 'สถานศึกษาของคุณยังไม่เปิดใช้งานระบบ', 'user' => null);
        }

        // Students and graduates are the same people in the same table and
        // sign in identically; which screen they land on is decided by where
        // they are in their studies.
        $studying = arr($alumni, 'study_state', 'graduated') === 'studying';

        $this->startSession(array(
            'kind'              => 'alumni',
            'id'                => (int) $alumni['id'],
            'school_id'         => (int) $alumni['school_id'],
            'role'              => $studying ? 'student' : 'alumni',
            'name'              => trim($alumni['title'] . $alumni['first_name'] . ' ' . $alumni['last_name']),
            'school_name'       => $alumni['school_name'],
            'must_set_password' => (bool) $mustSetPassword,
        ));

        $upd = $this->db->prepare(
            'UPDATE `' . $this->t('alumni') . '` SET last_login_at = ? WHERE id = ?'
        );
        $upd->execute(array(date('Y-m-d H:i:s'), $alumni['id']));

        if (array_key_exists('login_failures', $alumni)) {
            $reset = $this->db->prepare(
                'UPDATE `' . $this->t('alumni') . '` SET login_failures = 0, locked_until = NULL WHERE id = ?'
            );
            $reset->execute(array($alumni['id']));
        }

        return array('ok' => true, 'error' => '', 'user' => $alumni);
    }

    /**
     * Whether students and graduates need the teacher's one-time code.
     * @return bool
     */
    public function accessCodeRequired()
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT setting_value FROM `' . $this->t('settings') . '` WHERE setting_key = ? LIMIT 1'
            );
            $stmt->execute(array('alumni_access_code_required'));
            $value = $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
        return $value === '1';
    }

    /**
     * True for a session opened with a one-time code that has not chosen a
     * password yet.
     * @return bool
     */
    public function mustSetPassword()
    {
        $u = $this->user();
        return $u !== null && !empty($u['must_set_password']);
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

    /**
     * Signs in as another account while remembering who is really here.
     *
     * The original identity is kept aside, so leaving the session — by the
     * explicit button or by signing out — hands the administrator their own
     * account back rather than dropping them at the login screen.
     *
     * @param array $user row from `users`
     * @param array $school the target's institution, for the sidebar
     */
    public function startImpersonation($user, $school = null)
    {
        $original = $this->user();

        $this->startSession(array(
            'kind'        => 'staff',
            'id'          => (int) $user['id'],
            'school_id'   => $user['school_id'] === null ? null : (int) $user['school_id'],
            'role'        => $user['role'],
            'name'        => $user['full_name'],
            'school_name' => $school !== null ? $school['name'] : null,
        ));

        // Written after startSession(), which replaces the session contents.
        $_SESSION['impersonator'] = $original;
    }

    /**
     * Returns to the administrator who started the impersonation.
     *
     * @return bool false when nobody was being impersonated
     */
    public function stopImpersonation()
    {
        if (!isset($_SESSION['impersonator']) || !is_array($_SESSION['impersonator'])) {
            return false;
        }
        $original = $_SESSION['impersonator'];

        // The administrator's own account is re-read rather than restored from
        // the session: it may have been suspended or removed meanwhile.
        $stmt = $this->db->prepare(
            'SELECT * FROM `' . $this->t('users') . '` WHERE id = ? LIMIT 1'
        );
        $stmt->execute(array((int) arr($original, 'id', 0)));
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active' || $user['role'] !== 'centraladmin') {
            $this->logout();
            return false;
        }

        $this->startSession(array(
            'kind'        => 'staff',
            'id'          => (int) $user['id'],
            'school_id'   => $user['school_id'] === null ? null : (int) $user['school_id'],
            'role'        => $user['role'],
            'name'        => $user['full_name'],
            'school_name' => arr($original, 'school_name'),
        ));

        // Cleared last: session_regenerate_id() carries the session contents
        // across, so startSession() leaves this key untouched. Without this the
        // banner would stay up and a further impersonation would be refused as
        // one already in progress.
        unset($_SESSION['impersonator']);

        return true;
    }

    /**
     * @return bool
     */
    public function isImpersonating()
    {
        return isset($_SESSION['impersonator']) && is_array($_SESSION['impersonator']);
    }

    /**
     * The administrator behind an impersonated session.
     * @return array|null
     */
    public function impersonator()
    {
        return $this->isImpersonating() ? $_SESSION['impersonator'] : null;
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
        // A session opened with a one-time code reaches nothing else until it
        // has chosen a password; otherwise the code would be a login in itself.
        if ($this->mustSetPassword()) {
            redirect('account/set-password');
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
        if ($this->mustSetPassword()) {
            return 'account/set-password';
        }
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
