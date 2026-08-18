<?php
/**
 * Institution administration: staff accounts, departments, alumni roster and
 * the CSV import.
 */
class SchoolAdminController extends Controller
{
    const PER_PAGE = 50;
    const MAX_UPLOAD = 5242880; // 5 MB

    public function users()
    {
        $this->auth->require_role('schooladmin');
        $schoolId = $this->auth->schoolId();

        $this->render('schooladmin/users', array(
            'title'       => 'ผู้ใช้งาน',
            'users'       => $this->repo->usersForSchool($schoolId),
            'departments' => $this->repo->departments($schoolId),
            'school'      => $this->currentSchool(),
            'showForm'    => query('new') === '1',
            'old'         => array(),
        ));
    }

    public function userCreate()
    {
        $this->auth->require_role('schooladmin');
        csrf_verify();

        $schoolId = $this->auth->schoolId();
        $email = post('email');
        $password = post('password');
        $role = post('role', 'advisor');

        $allowed = staff_roles();
        unset($allowed['centraladmin']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'รูปแบบอีเมลไม่ถูกต้อง');
            redirect(url('schooladmin', array('new' => 1)));
        }
        if ($this->repo->userByEmail($email) !== null) {
            flash('error', 'อีเมลนี้ถูกใช้งานแล้วในระบบ');
            redirect(url('schooladmin', array('new' => 1)));
        }
        if (mb_strlen($password) < 8) {
            flash('error', 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
            redirect(url('schooladmin', array('new' => 1)));
        }
        if (!isset($allowed[$role])) {
            flash('error', 'บทบาทที่เลือกไม่ถูกต้อง');
            redirect(url('schooladmin', array('new' => 1)));
        }

        $departmentId = post_int('department_id', 0);

        $this->repo->createUser(array(
            'school_id'     => $schoolId,
            'department_id' => $departmentId > 0 ? $departmentId : null,
            'role'          => $role,
            'email'         => $email,
            'password'      => $password,
            'full_name'     => post('full_name'),
            'phone'         => post('phone'),
            'status'        => 'active',
        ));

        $this->repo->audit('user.create', $email, $role, $this->actor());
        flash('success', 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว');
        redirect('schooladmin');
    }

    public function userStatus()
    {
        $this->auth->require_role('schooladmin');
        csrf_verify();

        $id = post_int('id', 0);
        $status = post('status');
        if (!in_array($status, array('active', 'suspended'), true)) {
            flash('error', 'สถานะไม่ถูกต้อง');
            redirect('schooladmin');
        }

        $user = $this->repo->user($id);
        if ($user === null || (int) $user['school_id'] !== (int) $this->auth->schoolId()) {
            flash('error', 'ไม่พบผู้ใช้งานรายนี้ในสถานศึกษาของคุณ');
            redirect('schooladmin');
        }
        if ((int) $user['id'] === $this->auth->id()) {
            flash('error', 'ไม่สามารถเปลี่ยนสถานะบัญชีของตนเองได้');
            redirect('schooladmin');
        }

        $this->repo->setUserStatus($id, $status);
        $this->repo->audit('user.status', $user['email'], $status, $this->actor());
        flash('success', 'ปรับสถานะผู้ใช้งานเรียบร้อยแล้ว');
        redirect('schooladmin');
    }

    public function departments()
    {
        $this->auth->require_role('schooladmin');
        $schoolId = $this->auth->schoolId();

        if (is_post()) {
            csrf_verify();
            $name = post('name');
            if ($name === '') {
                flash('error', 'กรุณากรอกชื่อสาขาวิชา');
                redirect('schooladmin/departments');
            }
            $existing = $this->repo->one(
                'SELECT id FROM `{p}departments` WHERE school_id = ? AND name = ?',
                array($schoolId, $name)
            );
            if ($existing !== null) {
                flash('error', 'มีสาขาวิชาชื่อนี้อยู่แล้ว');
                redirect('schooladmin/departments');
            }
            $this->repo->createDepartment($schoolId, $name, post('code'));
            $this->repo->audit('department.create', $name, null, $this->actor());
            flash('success', 'เพิ่มสาขาวิชาเรียบร้อยแล้ว');
            redirect('schooladmin/departments');
        }

        $departments = $this->repo->all(
            'SELECT d.*, (SELECT COUNT(*) FROM `{p}alumni` a WHERE a.department_id = d.id) AS alumni_count'
            . ' FROM `{p}departments` d WHERE d.school_id = ?'
            . ' ORDER BY d.sort_order ASC, d.name ASC',
            array($schoolId)
        );

        $this->render('schooladmin/departments', array(
            'title'       => 'จัดการสาขา',
            'departments' => $departments,
        ));
    }

    public function alumni()
    {
        $this->auth->require_role('schooladmin');
        $schoolId = $this->auth->schoolId();
        $page = max(1, query_int('page', 1));

        $filters = array(
            'school_id'     => $schoolId,
            'survey_year'   => $this->repo->surveyYear(),
            'search'        => query('q'),
            'state'         => query('state'),
            'department_id' => query_int('dept', 0),
            'limit'         => self::PER_PAGE,
            'offset'        => ($page - 1) * self::PER_PAGE,
        );

        $this->render('schooladmin/alumni', array(
            'title'       => 'ข้อมูลศิษย์เก่า',
            'rows'        => $this->repo->alumniList($filters),
            'total'       => $this->repo->alumniCount($filters),
            'filters'     => $filters,
            'page'        => $page,
            'perPage'     => self::PER_PAGE,
            'departments' => $this->repo->departments($schoolId),
            'advisors'    => $this->repo->advisors($schoolId),
        ));
    }

    public function import()
    {
        $this->auth->require_role('schooladmin');

        if (query('template') === '1') {
            $this->csvDownload(
                'alumni-template.csv',
                array('student_code', 'national_id', 'title', 'first_name', 'last_name',
                    'department', 'level', 'graduation_year', 'phone', 'email', 'line_id', 'address'),
                array(
                    array('6231010001', '1234567890123', 'นาย', 'กิตติพงศ์', 'ใจดี',
                        'ช่างยนต์', 'ปวส.', '2567', '0812345678', 'kit@example.com', '', 'เพชรบูรณ์'),
                )
            );
            return;
        }

        $result = null;
        if (is_post()) {
            csrf_verify();
            $result = $this->runImport();
        }

        $this->render('schooladmin/import', array(
            'title'       => 'นำเข้าข้อมูล',
            'result'      => $result,
            'departments' => $this->repo->departments($this->auth->schoolId()),
        ));
    }

    /**
     * Parses the uploaded CSV and inserts the rows.
     *
     * @return array created / skipped / failed / errors
     */
    private function runImport()
    {
        $out = array('created' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => array());

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $out['errors'][] = 'อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่';
            $out['failed'] = 1;
            return $out;
        }
        if ($_FILES['file']['size'] > self::MAX_UPLOAD) {
            $out['errors'][] = 'ไฟล์มีขนาดเกิน 5 MB';
            $out['failed'] = 1;
            return $out;
        }

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if ($handle === false) {
            $out['errors'][] = 'เปิดไฟล์ไม่ได้';
            $out['failed'] = 1;
            return $out;
        }

        $schoolId = (int) $this->auth->schoolId();
        $defaultYear = post_int('graduation_year', current_academic_year());
        $updateExisting = post('update_existing') === '1';

        // Existing departments, matched by name so a spreadsheet can just use
        // the human-readable value.
        $departments = array();
        foreach ($this->repo->departments($schoolId) as $dept) {
            $departments[$this->normalise($dept['name'])] = (int) $dept['id'];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $out['errors'][] = 'ไฟล์ว่างเปล่า';
            $out['failed'] = 1;
            return $out;
        }
        // Strip a UTF-8 BOM that Excel likes to add to the first cell.
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $map = array();
        foreach ($header as $index => $name) {
            $map[$this->normalise($name)] = $index;
        }
        foreach (array('student_code', 'first_name', 'last_name') as $required) {
            if (!isset($map[$required])) {
                fclose($handle);
                $out['errors'][] = 'ไฟล์ขาดคอลัมน์ที่จำเป็น: ' . $required;
                $out['failed'] = 1;
                return $out;
            }
        }

        $line = 1;
        $db = $this->repo->db();

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            // Skip blank lines rather than reporting them as errors.
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $get = function ($key, $default = '') use ($row, $map) {
                if (!isset($map[$key]) || !isset($row[$map[$key]])) {
                    return $default;
                }
                return trim((string) $row[$map[$key]]);
            };

            $studentCode = $get('student_code');
            if ($studentCode === '') {
                $out['failed']++;
                $out['errors'][] = 'บรรทัด ' . $line . ': ไม่มีรหัสนักศึกษา';
                continue;
            }

            $existing = $this->repo->one(
                'SELECT id FROM `{p}alumni` WHERE school_id = ? AND student_code = ?',
                array($schoolId, $studentCode)
            );
            if ($existing !== null && !$updateExisting) {
                $out['skipped']++;
                continue;
            }

            $nationalId = preg_replace('/\D/', '', $get('national_id'));
            if ($existing === null && $nationalId === '') {
                $out['failed']++;
                $out['errors'][] = 'บรรทัด ' . $line . ' (' . $studentCode . '): ไม่มีเลขบัตรประชาชน';
                continue;
            }
            if ($nationalId !== '' && strlen($nationalId) !== 13) {
                $out['failed']++;
                $out['errors'][] = 'บรรทัด ' . $line . ' (' . $studentCode . '): เลขบัตรประชาชนไม่ครบ 13 หลัก';
                continue;
            }

            // Departments named in the file but not yet defined are created,
            // so importing a roster does not need a separate setup pass.
            $departmentId = null;
            $departmentName = $get('department');
            if ($departmentName !== '') {
                $key = $this->normalise($departmentName);
                if (!isset($departments[$key])) {
                    $departments[$key] = $this->repo->createDepartment($schoolId, $departmentName);
                }
                $departmentId = $departments[$key];
            }

            $gradYear = (int) $get('graduation_year', '0');
            if ($gradYear < 2400) {
                $gradYear = $defaultYear;
            }

            try {
                if ($existing !== null) {
                    $db->beginTransaction();
                    $this->repo->run(
                        'UPDATE `{p}alumni` SET department_id = ?, title = ?, first_name = ?,'
                        . ' last_name = ?, level = ?, graduation_year = ?, phone = ?, email = ?,'
                        . ' line_id = ?, address = ?, updated_at = ? WHERE id = ?',
                        array(
                            $departmentId, $get('title'), $get('first_name'), $get('last_name'),
                            $get('level'), $gradYear, $get('phone'), $get('email'),
                            $get('line_id'), $get('address'), date('Y-m-d H:i:s'), $existing['id'],
                        )
                    );
                    if ($nationalId !== '') {
                        $this->repo->run(
                            'UPDATE `{p}alumni` SET national_id_hash = ?, national_id_last4 = ?'
                            . ' WHERE id = ?',
                            array(
                                password_hash($nationalId, PASSWORD_DEFAULT, array('cost' => 10)),
                                substr($nationalId, -4),
                                $existing['id'],
                            )
                        );
                    }
                    $db->commit();
                } else {
                    $this->repo->createAlumni(array(
                        'school_id'       => $schoolId,
                        'department_id'   => $departmentId,
                        'student_code'    => $studentCode,
                        'national_id'     => $nationalId,
                        'title'           => $get('title'),
                        'first_name'      => $get('first_name'),
                        'last_name'       => $get('last_name'),
                        'level'           => $get('level'),
                        'graduation_year' => $gradYear,
                        'phone'           => $get('phone'),
                        'email'           => $get('email'),
                        'line_id'         => $get('line_id'),
                        'address'         => $get('address'),
                    ));
                }
                $out['created']++;
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $out['failed']++;
                $out['errors'][] = 'บรรทัด ' . $line . ' (' . $studentCode . '): ' . $e->getMessage();
            }

            // Keep the error list from growing without bound on a bad file.
            if (count($out['errors']) > 100) {
                $out['errors'][] = '... (แสดงเฉพาะ 100 รายการแรก)';
                break;
            }
        }

        fclose($handle);
        $this->repo->audit(
            'alumni.import',
            $_FILES['file']['name'],
            'created=' . $out['created'] . ' skipped=' . $out['skipped'] . ' failed=' . $out['failed'],
            $this->actor()
        );
        return $out;
    }

    /**
     * Lower-cased, trimmed key for header and department matching.
     * @param string $value
     * @return string
     */
    private function normalise($value)
    {
        return mb_strtolower(trim((string) $value));
    }
}
