<?php
/**
 * All database access lives here so the controllers stay readable and every
 * query is written once against the prefixed table names.
 */
class Repository
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $prefix;

    public function __construct(PDO $db, $prefix)
    {
        $this->db = $db;
        $this->prefix = (string) $prefix;
    }

    private function t($name)
    {
        return $this->prefix . $name;
    }

    /** @return PDO */
    public function db()
    {
        return $this->db;
    }

    /**
     * Prepare + execute, substituting `{p}` with the table prefix.
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function run($sql, $params = array())
    {
        $stmt = $this->db->prepare(str_replace('{p}', $this->prefix, $sql));
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array|null */
    public function one($sql, $params = array())
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return array */
    public function all($sql, $params = array())
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return mixed first column of the first row */
    public function scalar($sql, $params = array())
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }

    public function lastId()
    {
        return (int) $this->db->lastInsertId();
    }

    // ------------------------------------------------------------- settings

    public function setting($key, $default = null)
    {
        $row = $this->one('SELECT setting_value FROM `{p}settings` WHERE setting_key = ?', array($key));
        return $row === null ? $default : $row['setting_value'];
    }

    public function setSetting($key, $value)
    {
        $this->run(
            'INSERT INTO `{p}settings` (setting_key, setting_value, updated_at) VALUES (?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
            array($key, (string) $value, date('Y-m-d H:i:s'))
        );
    }

    /**
     * The survey year the system is currently collecting for (Buddhist year).
     * @return int
     */
    public function surveyYear()
    {
        $year = (int) $this->setting('survey_year', 0);
        return $year > 0 ? $year : current_academic_year();
    }

    /**
     * Whether an institution may sign itself up from the public site.
     *
     * The central administrator closes this once every institution that
     * belongs in the system is in it, so the requests screen stops filling up
     * with sign-ups nobody is expecting.
     *
     * @return bool
     */
    public function registrationOpen()
    {
        return $this->setting('allow_school_register', '1') === '1';
    }

    // -------------------------------------------------------------- schools

    public function schools($status = null)
    {
        if ($status !== null) {
            return $this->all(
                'SELECT * FROM `{p}schools` WHERE status = ? ORDER BY name ASC',
                array($status)
            );
        }
        return $this->all('SELECT * FROM `{p}schools` ORDER BY status ASC, name ASC');
    }

    /**
     * Schools with their alumni counts, for the central admin table.
     * @return array
     */
    public function schoolsWithCounts()
    {
        return $this->all(
            'SELECT s.*,'
            . ' (SELECT COUNT(*) FROM `{p}alumni` a WHERE a.school_id = s.id'
            . '    AND a.study_state = "graduated") AS alumni_count,'
            . ' (SELECT COUNT(*) FROM `{p}alumni` a WHERE a.school_id = s.id'
            . '    AND a.study_state = "studying") AS student_count'
            . ' FROM `{p}schools` s ORDER BY FIELD(s.status, "pending", "active", "suspended"), s.name ASC'
        );
    }

    /**
     * @param string $name
     * @return array|null
     */
    public function schoolByName($name)
    {
        return $this->one('SELECT * FROM `{p}schools` WHERE name = ?', array(trim((string) $name)));
    }

    public function school($id)
    {
        return $this->one('SELECT * FROM `{p}schools` WHERE id = ?', array((int) $id));
    }

    /**
     * @param array $data
     * @return int new school id
     */
    public function createSchool($data)
    {
        $now = date('Y-m-d H:i:s');
        $this->run(
            'INSERT INTO `{p}schools`'
            . ' (code, name, province, affiliation, rms_base_url, contact_name, contact_phone,'
            . '  contact_email, status, note, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                arr($data, 'code', ''),
                arr($data, 'name', ''),
                arr($data, 'province', ''),
                arr($data, 'affiliation', ''),
                rtrim(trim((string) arr($data, 'rms_base_url', '')), '/'),
                arr($data, 'contact_name', ''),
                arr($data, 'contact_phone', ''),
                arr($data, 'contact_email', ''),
                arr($data, 'status', 'pending'),
                arr($data, 'note', null),
                $now, $now,
            )
        );
        return $this->lastId();
    }

    /**
     * The RMS address to use for an institution.
     *
     * Its own address wins; the system-wide setting is the fallback, so a
     * single-institution deployment can be configured in one place.
     *
     * @param int|null $schoolId
     * @return string
     */
    public function rmsBaseUrlFor($schoolId)
    {
        if ($schoolId !== null && (int) $schoolId > 0) {
            $school = $this->school((int) $schoolId);
            if ($school !== null && trim((string) arr($school, 'rms_base_url', '')) !== '') {
                return rtrim(trim($school['rms_base_url']), '/');
            }
        }
        return rtrim(trim((string) $this->setting('rms_base_url', '')), '/');
    }

    /**
     * @param int $id
     * @param string $url
     */
    public function setSchoolRmsUrl($id, $url)
    {
        $this->run(
            'UPDATE `{p}schools` SET rms_base_url = ?, updated_at = ? WHERE id = ?',
            array(rtrim(trim((string) $url), '/'), date('Y-m-d H:i:s'), (int) $id)
        );
    }

    public function setSchoolStatus($id, $status)
    {
        $this->run(
            'UPDATE `{p}schools` SET status = ?, updated_at = ? WHERE id = ?',
            array($status, date('Y-m-d H:i:s'), (int) $id)
        );
    }

    // ---------------------------------------------------------- departments

    public function departments($schoolId)
    {
        return $this->all(
            'SELECT * FROM `{p}departments` WHERE school_id = ? ORDER BY sort_order ASC, name ASC',
            array((int) $schoolId)
        );
    }

    public function createDepartment($schoolId, $name, $code = '', $sortOrder = 0)
    {
        $this->run(
            'INSERT INTO `{p}departments` (school_id, code, name, sort_order, created_at)'
            . ' VALUES (?, ?, ?, ?, ?)',
            array((int) $schoolId, $code, $name, (int) $sortOrder, date('Y-m-d H:i:s'))
        );
        return $this->lastId();
    }

    // ----------------------------------------------------------- staff users

    public function usersForSchool($schoolId)
    {
        return $this->all(
            'SELECT u.*, d.name AS department_name FROM `{p}users` u'
            . ' LEFT JOIN `{p}departments` d ON d.id = u.department_id'
            . ' WHERE u.school_id = ? ORDER BY FIELD(u.status, "pending", "active", "suspended"), u.full_name ASC',
            array((int) $schoolId)
        );
    }

    public function user($id)
    {
        return $this->one('SELECT * FROM `{p}users` WHERE id = ?', array((int) $id));
    }

    public function userByEmail($email)
    {
        return $this->one('SELECT * FROM `{p}users` WHERE email = ?', array($email));
    }

    /**
     * @param array $data
     * @return int new user id
     */
    public function createUser($data)
    {
        $now = date('Y-m-d H:i:s');
        $username = arr($data, 'username', '');
        $this->run(
            'INSERT INTO `{p}users`'
            . ' (school_id, department_id, role, username, email, password_hash, full_name,'
            . '  phone, status, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                arr($data, 'school_id') === null ? null : (int) $data['school_id'],
                arr($data, 'department_id') === null ? null : (int) $data['department_id'],
                arr($data, 'role', 'advisor'),
                $username === '' ? null : $username,
                arr($data, 'email', ''),
                password_hash(arr($data, 'password', ''), PASSWORD_DEFAULT, array('cost' => 10)),
                arr($data, 'full_name', ''),
                arr($data, 'phone', ''),
                arr($data, 'status', 'active'),
                $now, $now,
            )
        );
        return $this->lastId();
    }

    /**
     * @param string $username
     * @return array|null
     */
    public function userByUsername($username)
    {
        if ((string) $username === '') {
            return null;
        }
        return $this->one('SELECT * FROM `{p}users` WHERE username = ?', array($username));
    }

    /**
     * @param string $source
     * @param string $externalId
     * @return array|null
     */
    public function userByExternal($source, $externalId)
    {
        return $this->one(
            'SELECT * FROM `{p}users` WHERE external_source = ? AND external_id = ?',
            array($source, $externalId)
        );
    }

    /**
     * Creates or refreshes a user transferred from an external system.
     *
     * The row is located by its identifier in the source system, then by
     * username, then by email, so a repeat transfer refreshes the same person
     * and a person already entered by hand is adopted rather than duplicated.
     *
     * created_at is written once and never touched again, so a repeat transfer
     * does not rewrite when the account first appeared. role and school_id are
     * likewise only set on creation: an administrator may have changed them
     * here afterwards, and the source system does not know about them.
     *
     * @param array $data
     * @param bool $updatePassword whether an existing row's password is replaced
     * @return array array('id'=>int, 'created'=>bool, 'avatar_path'=>string)
     */
    public function upsertImportedUser($data, $updatePassword = false)
    {
        $now = date('Y-m-d H:i:s');
        $source = arr($data, 'external_source', '');
        $externalId = arr($data, 'external_id', '');
        $username = arr($data, 'username', '');
        $email = arr($data, 'email');

        $existing = $this->userByExternal($source, $externalId);
        if ($existing === null) {
            $existing = $this->userByUsername($username);
        }
        if ($existing === null && $email !== null && $email !== '') {
            $existing = $this->userByEmail($email);
        }

        if ($existing !== null) {
            $sql = 'UPDATE `{p}users` SET full_name = ?, email = ?, phone = ?,'
                . ' username = ?, external_source = ?, external_id = ?, updated_at = ?';
            $params = array(
                arr($data, 'full_name', ''),
                $email,
                arr($data, 'phone', ''),
                $username === '' ? null : $username,
                $source,
                $externalId,
                $now,
            );
            if ($updatePassword) {
                $sql .= ', password_hash = ?';
                $params[] = password_hash(arr($data, 'password', ''), PASSWORD_DEFAULT, array('cost' => 10));
            }
            $sql .= ' WHERE id = ?';
            $params[] = (int) $existing['id'];

            $this->run($sql, $params);

            return array(
                'id'          => (int) $existing['id'],
                'created'     => false,
                'avatar_path' => isset($existing['avatar_path']) ? (string) $existing['avatar_path'] : '',
            );
        }

        $this->run(
            'INSERT INTO `{p}users`'
            . ' (school_id, role, username, email, password_hash, full_name, phone,'
            . '  status, external_source, external_id, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                arr($data, 'school_id') === null ? null : (int) $data['school_id'],
                arr($data, 'role', 'advisor'),
                $username === '' ? null : $username,
                $email,
                password_hash(arr($data, 'password', ''), PASSWORD_DEFAULT, array('cost' => 10)),
                arr($data, 'full_name', ''),
                arr($data, 'phone', ''),
                arr($data, 'status', 'active'),
                $source,
                $externalId,
                $now, $now,
            )
        );

        return array('id' => $this->lastId(), 'created' => true, 'avatar_path' => '');
    }

    /**
     * @param int $id
     * @param string $filename
     */
    public function setUserAvatar($id, $filename)
    {
        $this->run(
            'UPDATE `{p}users` SET avatar_path = ?, updated_at = ? WHERE id = ?',
            array($filename, date('Y-m-d H:i:s'), (int) $id)
        );
    }

    /**
     * Transferred users whose picture has not been downloaded yet.
     *
     * @param string $source
     * @param int $limit
     * @return array
     */
    public function usersMissingAvatar($source, $limit = 500)
    {
        return $this->all(
            'SELECT id, external_id FROM `{p}users`'
            . ' WHERE external_source = ? AND avatar_path = "" ORDER BY id ASC'
            . ' LIMIT ' . (int) $limit,
            array($source)
        );
    }

    public function setUserStatus($id, $status)
    {
        $this->run(
            'UPDATE `{p}users` SET status = ?, updated_at = ? WHERE id = ?',
            array($status, date('Y-m-d H:i:s'), (int) $id)
        );
    }

    public function setUserPassword($id, $plainPassword)
    {
        $this->run(
            'UPDATE `{p}users` SET password_hash = ?, updated_at = ? WHERE id = ?',
            array(
                password_hash($plainPassword, PASSWORD_DEFAULT, array('cost' => 10)),
                date('Y-m-d H:i:s'),
                (int) $id,
            )
        );
    }

    /**
     * The fields a staff member may change about themselves.
     *
     * Deliberately narrow: role, school, department and status decide what an
     * account can reach, so they stay with the administrator who granted them
     * and are not writable from the account's own screen.
     *
     * @param int $id
     * @param array $data full_name, email, phone
     */
    public function updateUserProfile($id, $data)
    {
        $this->run(
            'UPDATE `{p}users` SET full_name = ?, email = ?, phone = ?, updated_at = ?'
            . ' WHERE id = ?',
            array(
                arr($data, 'full_name', ''),
                arr($data, 'email', ''),
                arr($data, 'phone', ''),
                date('Y-m-d H:i:s'),
                (int) $id,
            )
        );
    }

    /** @return array advisors of one school, for assignment dropdowns */
    public function advisors($schoolId)
    {
        return $this->all(
            'SELECT id, full_name FROM `{p}users` WHERE school_id = ? AND role = ? AND status = ?'
            . ' ORDER BY full_name ASC',
            array((int) $schoolId, 'advisor', 'active')
        );
    }

    // --------------------------------------------------------------- alumni

    public function alumni($id)
    {
        return $this->one(
            'SELECT a.*, d.name AS department_name, s.name AS school_name'
            . ' FROM `{p}alumni` a'
            . ' LEFT JOIN `{p}departments` d ON d.id = a.department_id'
            . ' LEFT JOIN `{p}schools` s ON s.id = a.school_id'
            . ' WHERE a.id = ?',
            array((int) $id)
        );
    }

    /**
     * Alumni list with their status for one survey year.
     *
     * @param array $filters school_id, advisor_id, department_id,
     *                       graduation_year, search, state, limit, offset
     * @return array
     */
    public function alumniList($filters = array())
    {
        $year = (int) arr($filters, 'survey_year', $this->surveyYear());
        $where = array('a.school_id = ?');
        $params = array((int) arr($filters, 'school_id', 0));

        if (arr($filters, 'advisor_id')) {
            $where[] = 'a.advisor_user_id = ?';
            $params[] = (int) $filters['advisor_id'];
        }
        if (arr($filters, 'department_id')) {
            $where[] = 'a.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (arr($filters, 'graduation_year')) {
            $where[] = 'a.graduation_year = ?';
            $params[] = (int) $filters['graduation_year'];
        }
        // Unset means both groups; the roster screen shows them together and
        // the advisor screen asks for graduates only.
        $studyState = (string) arr($filters, 'study_state', '');
        $knownStates = study_states();
        if ($studyState !== '' && isset($knownStates[$studyState])) {
            $where[] = 'a.study_state = ?';
            $params[] = $studyState;
        }
        $search = trim((string) arr($filters, 'search', ''));
        if ($search !== '') {
            $where[] = '(a.student_code LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $state = (string) arr($filters, 'state', '');
        if ($state === 'updated') {
            $where[] = 'st.id IS NOT NULL AND st.is_draft = 0';
        } elseif ($state === 'pending') {
            $where[] = '(st.id IS NULL OR st.is_draft = 1)';
        } elseif ($state === 'unreachable') {
            $where[] = 'a.contact_state = "unreachable"';
        }

        $limit = (int) arr($filters, 'limit', 50);
        $offset = (int) arr($filters, 'offset', 0);
        if ($limit < 1) {
            $limit = 50;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        // survey_year is bound first because it sits in the JOIN clause.
        $sql = 'SELECT a.*, d.name AS department_name, st.employment_status, st.is_draft,'
            . ' st.submitted_at, st.company_name, st.study_place, u.full_name AS advisor_name'
            . ' FROM `{p}alumni` a'
            . ' LEFT JOIN `{p}departments` d ON d.id = a.department_id'
            . ' LEFT JOIN `{p}users` u ON u.id = a.advisor_user_id'
            . ' LEFT JOIN `{p}alumni_status` st ON st.alumni_id = a.id AND st.survey_year = ?'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY a.student_code ASC'
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->all($sql, array_merge(array($year), $params));
    }

    /**
     * Row count for the same filters, for pagination.
     * @return int
     */
    public function alumniCount($filters = array())
    {
        $year = (int) arr($filters, 'survey_year', $this->surveyYear());
        $where = array('a.school_id = ?');
        $params = array((int) arr($filters, 'school_id', 0));

        if (arr($filters, 'advisor_id')) {
            $where[] = 'a.advisor_user_id = ?';
            $params[] = (int) $filters['advisor_id'];
        }
        if (arr($filters, 'department_id')) {
            $where[] = 'a.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (arr($filters, 'graduation_year')) {
            $where[] = 'a.graduation_year = ?';
            $params[] = (int) $filters['graduation_year'];
        }
        // Unset means both groups; the roster screen shows them together and
        // the advisor screen asks for graduates only.
        $studyState = (string) arr($filters, 'study_state', '');
        $knownStates = study_states();
        if ($studyState !== '' && isset($knownStates[$studyState])) {
            $where[] = 'a.study_state = ?';
            $params[] = $studyState;
        }
        $search = trim((string) arr($filters, 'search', ''));
        if ($search !== '') {
            $where[] = '(a.student_code LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $state = (string) arr($filters, 'state', '');
        if ($state === 'updated') {
            $where[] = 'st.id IS NOT NULL AND st.is_draft = 0';
        } elseif ($state === 'pending') {
            $where[] = '(st.id IS NULL OR st.is_draft = 1)';
        } elseif ($state === 'unreachable') {
            $where[] = 'a.contact_state = "unreachable"';
        }

        $sql = 'SELECT COUNT(*) FROM `{p}alumni` a'
            . ' LEFT JOIN `{p}alumni_status` st ON st.alumni_id = a.id AND st.survey_year = ?'
            . ' WHERE ' . implode(' AND ', $where);

        return (int) $this->scalar($sql, array_merge(array($year), $params));
    }

    /**
     * @param array $data
     * @return int new alumni id
     */
    public function createAlumni($data)
    {
        $now = date('Y-m-d H:i:s');
        $nationalId = preg_replace('/\D/', '', (string) arr($data, 'national_id', ''));
        $this->run(
            'INSERT INTO `{p}alumni`'
            . ' (school_id, department_id, advisor_user_id, student_code, national_id_hash,'
            . '  national_id_last4, title, first_name, last_name, level, graduation_year,'
            . '  phone, email, line_id, address, status, study_state, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                (int) arr($data, 'school_id', 0),
                arr($data, 'department_id') === null ? null : (int) $data['department_id'],
                arr($data, 'advisor_user_id') === null ? null : (int) $data['advisor_user_id'],
                arr($data, 'student_code', ''),
                $nationalId === '' ? '' : password_hash($nationalId, PASSWORD_DEFAULT, array('cost' => 10)),
                $nationalId === '' ? '' : substr($nationalId, -4),
                arr($data, 'title', ''),
                arr($data, 'first_name', ''),
                arr($data, 'last_name', ''),
                arr($data, 'level', ''),
                (int) arr($data, 'graduation_year', 0),
                arr($data, 'phone', ''),
                arr($data, 'email', ''),
                arr($data, 'line_id', ''),
                arr($data, 'address', null),
                arr($data, 'status', 'active'),
                arr($data, 'study_state', 'graduated') === 'studying' ? 'studying' : 'graduated',
                $now, $now,
            )
        );
        return $this->lastId();
    }

    public function updateAlumniContact($id, $data)
    {
        $this->run(
            'UPDATE `{p}alumni` SET phone = ?, email = ?, line_id = ?, address = ?, updated_at = ?'
            . ' WHERE id = ?',
            array(
                arr($data, 'phone', ''),
                arr($data, 'email', ''),
                arr($data, 'line_id', ''),
                arr($data, 'address', null),
                date('Y-m-d H:i:s'),
                (int) $id,
            )
        );
    }

    /**
     * What a current student intends to do after graduating.
     *
     * @param int $id
     * @param string $plan a key of graduation_plans(), or '' for not yet said
     * @param string $note
     */
    public function updateAlumniPlan($id, $plan, $note = '')
    {
        $plans = graduation_plans();
        if ($plan !== '' && !isset($plans[$plan])) {
            $plan = '';
        }
        $this->run(
            'UPDATE `{p}alumni` SET plan_after = ?, plan_note = ?, updated_at = ? WHERE id = ?',
            array($plan, $note, date('Y-m-d H:i:s'), (int) $id)
        );
    }

    /**
     * Moves someone between the current-student and graduate groups.
     *
     * Graduating is a flag, not a copy: it is the same person and the same
     * row, so nothing they have already filled in is lost when they finish.
     *
     * @param int $id
     * @param string $state a key of study_states()
     * @return bool false when the state is not one this system knows
     */
    public function setAlumniStudyState($id, $state)
    {
        $states = study_states();
        if (!isset($states[$state])) {
            return false;
        }
        $this->run(
            'UPDATE `{p}alumni` SET study_state = ?, updated_at = ? WHERE id = ?',
            array($state, date('Y-m-d H:i:s'), (int) $id)
        );
        return true;
    }

    public function setAlumniContactState($id, $state, $note = '')
    {
        $this->run(
            'UPDATE `{p}alumni` SET contact_state = ?, contact_note = ?, updated_at = ? WHERE id = ?',
            array($state, $note, date('Y-m-d H:i:s'), (int) $id)
        );
    }

    // -------------------------------------------------------- survey answers

    /**
     * @return array|null
     */
    public function statusFor($alumniId, $year)
    {
        return $this->one(
            'SELECT * FROM `{p}alumni_status` WHERE alumni_id = ? AND survey_year = ?',
            array((int) $alumniId, (int) $year)
        );
    }

    /**
     * Every year already recorded for one alumnus, newest first.
     * @return array
     */
    public function statusHistory($alumniId)
    {
        return $this->all(
            'SELECT * FROM `{p}alumni_status` WHERE alumni_id = ? ORDER BY survey_year DESC',
            array((int) $alumniId)
        );
    }

    /**
     * Insert or update the answer for one alumnus and year.
     *
     * @param int $alumniId
     * @param int $schoolId
     * @param int $year
     * @param array $data
     * @param bool $isDraft
     * @param string $actorKind 'alumni' or 'staff'
     * @param int $actorId
     */
    public function saveStatus($alumniId, $schoolId, $year, $data, $isDraft, $actorKind, $actorId)
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->statusFor($alumniId, $year);

        $salary = arr($data, 'salary', '');
        $salary = ($salary === '' || $salary === null) ? null : (float) $salary;

        $fields = array(
            'employment_status' => arr($data, 'employment_status', ''),
            'company_name'      => arr($data, 'company_name', ''),
            'job_position'      => arr($data, 'job_position', ''),
            'salary'            => $salary,
            'salary_band'       => self::salaryBand($salary),
            'work_province'     => arr($data, 'work_province', ''),
            'study_place'       => arr($data, 'study_place', ''),
            'study_level'       => arr($data, 'study_level', ''),
            'study_major'       => arr($data, 'study_major', ''),
            'note'              => arr($data, 'note', null),
            'is_draft'          => $isDraft ? 1 : 0,
            'updated_by_kind'   => $actorKind,
            'updated_by_id'     => (int) $actorId,
            'updated_at'        => $now,
        );

        if ($existing) {
            $set = array();
            $params = array();
            foreach ($fields as $column => $value) {
                $set[] = '`' . $column . '` = ?';
                $params[] = $value;
            }
            // Stamp the submission time only when it stops being a draft.
            if (!$isDraft) {
                $set[] = '`submitted_at` = ?';
                $params[] = $now;
            }
            $params[] = (int) $existing['id'];
            $this->run(
                'UPDATE `{p}alumni_status` SET ' . implode(', ', $set) . ' WHERE id = ?',
                $params
            );
            return (int) $existing['id'];
        }

        $fields['alumni_id'] = (int) $alumniId;
        $fields['school_id'] = (int) $schoolId;
        $fields['survey_year'] = (int) $year;
        $fields['created_at'] = $now;
        $fields['submitted_at'] = $isDraft ? null : $now;

        $columns = array_keys($fields);
        $placeholders = array_fill(0, count($columns), '?');
        $this->run(
            'INSERT INTO `{p}alumni_status` (`' . implode('`, `', $columns) . '`)'
            . ' VALUES (' . implode(', ', $placeholders) . ')',
            array_values($fields)
        );
        return $this->lastId();
    }

    /**
     * @param float|null $salary
     * @return string
     */
    public static function salaryBand($salary)
    {
        if ($salary === null || $salary <= 0) {
            return '';
        }
        if ($salary < 10000) {
            return 'lt10k';
        }
        if ($salary < 15000) {
            return '10k-15k';
        }
        if ($salary < 20000) {
            return '15k-20k';
        }
        if ($salary < 30000) {
            return '20k-30k';
        }
        return 'gte30k';
    }

    // ------------------------------------------------------------ reporting

    /**
     * Headline numbers for one school and survey year.
     * @return array
     */
    public function schoolSummary($schoolId, $year, $graduationYear = 0)
    {
        $schoolId = (int) $schoolId;
        $year = (int) $year;

        // Current students are excluded everywhere in this section. They have
        // not finished yet, so counting them would drag the placement rate
        // down against a denominator that cannot answer the survey.
        $where = 'a.school_id = ? AND a.study_state = "graduated"';
        $params = array($schoolId);
        if ($graduationYear > 0) {
            $where .= ' AND a.graduation_year = ?';
            $params[] = $graduationYear;
        }

        $total = (int) $this->scalar(
            'SELECT COUNT(*) FROM `{p}alumni` a WHERE ' . $where,
            $params
        );

        $rows = $this->all(
            'SELECT st.employment_status AS code, COUNT(*) AS c'
            . ' FROM `{p}alumni` a'
            . ' JOIN `{p}alumni_status` st ON st.alumni_id = a.id AND st.survey_year = ?'
            . ' WHERE ' . $where . ' AND st.is_draft = 0'
            . ' GROUP BY st.employment_status',
            array_merge(array($year), $params)
        );

        $byStatus = array();
        foreach (array_keys(employment_statuses()) as $code) {
            $byStatus[$code] = 0;
        }
        $updated = 0;
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $count = (int) $row['c'];
            $updated += $count;
            if (isset($byStatus[$code])) {
                $byStatus[$code] = $count;
            }
        }

        $employed = $byStatus['employed_match'] + $byStatus['employed_other'] + $byStatus['freelance'];
        $study = $byStatus['study'];
        $other = $byStatus['unemployed'] + $byStatus['military'];

        return array(
            'total'     => $total,
            'updated'   => $updated,
            'pending'   => max(0, $total - $updated),
            'by_status' => $byStatus,
            'employed'  => $employed,
            'study'     => $study,
            'other'     => $other,
            // The headline "success" figure: working or still studying.
            'placed'    => $employed + $study,
        );
    }

    /**
     * Placement rate per department, for the dashboard bar list.
     * @return array
     */
    public function departmentBreakdown($schoolId, $year, $graduationYear = 0)
    {
        $params = array((int) $year, (int) $schoolId);
        $extra = '';
        if ($graduationYear > 0) {
            $extra = ' AND a.graduation_year = ?';
            $params[] = (int) $graduationYear;
        }

        $rows = $this->all(
            'SELECT d.id, d.name,'
            . ' COUNT(a.id) AS total,'
            . ' SUM(CASE WHEN st.is_draft = 0 AND st.employment_status IN'
            . '   ("employed_match","employed_other","freelance","study") THEN 1 ELSE 0 END) AS placed,'
            . ' SUM(CASE WHEN st.is_draft = 0 THEN 1 ELSE 0 END) AS answered'
            . ' FROM `{p}departments` d'
            // The graduate test belongs in the JOIN, not the WHERE: moved to
            // the WHERE it would turn this into an inner join and drop any
            // department whose students have all yet to graduate.
            . ' LEFT JOIN `{p}alumni` a ON a.department_id = d.id'
            . '   AND a.study_state = "graduated"'
            . ' LEFT JOIN `{p}alumni_status` st ON st.alumni_id = a.id AND st.survey_year = ?'
            . ' WHERE d.school_id = ?' . $extra
            . ' GROUP BY d.id, d.name ORDER BY d.sort_order ASC, d.name ASC',
            $params
        );

        $out = array();
        foreach ($rows as $row) {
            $answered = (int) $row['answered'];
            $placed = (int) $row['placed'];
            $out[] = array(
                'id'       => (int) $row['id'],
                'name'     => $row['name'],
                'total'    => (int) $row['total'],
                'answered' => $answered,
                'placed'   => $placed,
                'pct'      => $answered > 0 ? round(($placed / $answered) * 100) : 0,
            );
        }
        return $out;
    }

    /**
     * Placement rate for several graduation years, for the comparison screen.
     * @return array
     */
    public function yearComparison($schoolId, $limit = 5)
    {
        $rows = $this->all(
            'SELECT a.graduation_year AS y, COUNT(a.id) AS total,'
            . ' SUM(CASE WHEN st.is_draft = 0 THEN 1 ELSE 0 END) AS answered,'
            . ' SUM(CASE WHEN st.is_draft = 0 AND st.employment_status IN'
            . '   ("employed_match","employed_other","freelance","study") THEN 1 ELSE 0 END) AS placed'
            . ' FROM `{p}alumni` a'
            . ' LEFT JOIN `{p}alumni_status` st ON st.alumni_id = a.id'
            . ' WHERE a.school_id = ? AND a.graduation_year > 0'
            . '   AND a.study_state = "graduated"'
            . ' GROUP BY a.graduation_year ORDER BY a.graduation_year DESC'
            . ' LIMIT ' . (int) $limit,
            array((int) $schoolId)
        );
        $out = array();
        foreach (array_reverse($rows) as $row) {
            $answered = (int) $row['answered'];
            $out[] = array(
                'year'     => (int) $row['y'],
                'total'    => (int) $row['total'],
                'answered' => $answered,
                'placed'   => (int) $row['placed'],
                'pct'      => $answered > 0 ? round(((int) $row['placed'] / $answered) * 100) : 0,
            );
        }
        return $out;
    }

    /**
     * Graduation years present for one school, newest first.
     * @return array of int
     */
    public function graduationYears($schoolId)
    {
        $rows = $this->all(
            'SELECT DISTINCT graduation_year FROM `{p}alumni`'
            . ' WHERE school_id = ? AND graduation_year > 0'
            . '   AND study_state = "graduated"'
            . ' ORDER BY graduation_year DESC',
            array((int) $schoolId)
        );
        $out = array();
        foreach ($rows as $row) {
            $out[] = (int) $row['graduation_year'];
        }
        return $out;
    }

    /**
     * System-wide counters for the central admin screen and the landing page.
     * @return array
     */
    public function centralSummary()
    {
        $year = $this->surveyYear();
        $schools = (int) $this->scalar('SELECT COUNT(*) FROM `{p}schools`');
        $active = (int) $this->scalar('SELECT COUNT(*) FROM `{p}schools` WHERE status = ?', array('active'));
        $pending = (int) $this->scalar('SELECT COUNT(*) FROM `{p}schools` WHERE status = ?', array('pending'));
        $alumni = (int) $this->scalar(
            'SELECT COUNT(*) FROM `{p}alumni` WHERE study_state = "graduated"'
        );
        $students = (int) $this->scalar(
            'SELECT COUNT(*) FROM `{p}alumni` WHERE study_state = "studying"'
        );
        $answered = (int) $this->scalar(
            'SELECT COUNT(*) FROM `{p}alumni_status` WHERE survey_year = ? AND is_draft = 0',
            array($year)
        );
        $placed = (int) $this->scalar(
            'SELECT COUNT(*) FROM `{p}alumni_status` WHERE survey_year = ? AND is_draft = 0'
            . ' AND employment_status IN ("employed_match","employed_other","freelance","study")',
            array($year)
        );

        return array(
            'schools'         => $schools,
            'active_schools'  => $active,
            'pending_schools' => $pending,
            'alumni'          => $alumni,
            'students'        => $students,
            'answered'        => $answered,
            'placed'          => $placed,
            'placed_pct'      => $answered > 0 ? pct($placed, $answered) : '0.0',
            'survey_year'     => $year,
        );
    }

    // ------------------------------------------------------------ audit log

    /**
     * @param string $action
     * @param string $target
     * @param string|null $detail
     * @param array|null $actor session identity
     */
    public function audit($action, $target = '', $detail = null, $actor = null)
    {
        try {
            $this->run(
                'INSERT INTO `{p}audit_log`'
                . ' (school_id, actor_kind, actor_id, actor_name, action, target, detail, ip, created_at)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array(
                    $actor && isset($actor['school_id']) && $actor['school_id'] !== null
                        ? (int) $actor['school_id'] : null,
                    $actor ? arr($actor, 'kind', '') : '',
                    $actor ? (int) arr($actor, 'id', 0) : null,
                    $actor ? arr($actor, 'name', '') : '',
                    $action,
                    $target,
                    $detail,
                    isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '',
                    date('Y-m-d H:i:s'),
                )
            );
        } catch (PDOException $e) {
            // Auditing must never break the request it is recording.
            app_log('audit failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array
     */
    public function auditRecent($schoolId = null, $limit = 50)
    {
        $limit = (int) $limit;
        if ($schoolId === null) {
            return $this->all(
                'SELECT * FROM `{p}audit_log` ORDER BY id DESC LIMIT ' . $limit
            );
        }
        return $this->all(
            'SELECT * FROM `{p}audit_log` WHERE school_id = ? ORDER BY id DESC LIMIT ' . $limit,
            array((int) $schoolId)
        );
    }
}
