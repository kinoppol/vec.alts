<?php
/**
 * Optional demo data, so a fresh install has something to look at on every
 * screen. Never runs automatically — the installer offers it as a choice.
 */
class Seeder
{
    /**
     * Demo data created from the settings screen lives in its own namespace so
     * that removing it again can be exact.
     *
     * Two independent markers identify an institution as demo data, and both
     * have to agree before anything is deleted: its id is listed in the
     * DEMO_SETTING setting, and its code begins with DEMO_CODE_PREFIX. A
     * setting that has been edited by hand, or carried over from another
     * database, therefore cannot point the purge at a real institution.
     *
     * Staff who belong to no institution — the sample central administrator —
     * are recognised by their address instead. The TLD is one RFC 2606
     * reserves, so it can never collide with a real mailbox.
     */
    const DEMO_DOMAIN = 'demo.invalid';
    const DEMO_CODE_PREFIX = 'DEMO-';
    const DEMO_SETTING = 'demo_school_ids';

    /**
     * Above this many graduates outside the demo namespace, the installation
     * is treated as carrying real data: seeding then demands a typed
     * confirmation instead of going ahead on one click. Deliberately well
     * below the size of a single real cohort.
     */
    const REAL_DATA_THRESHOLD = 200;

    /** @var Repository */
    private $repo;

    public function __construct(Repository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @param string $password password for the sample staff accounts, chosen
     *                         by the administrator during installation
     * @return array summary counts
     */
    public function run($password)
    {
        // No default. A demo password baked into the source would be the same
        // on every installation, including any that reaches a real server.
        $password = (string) $password;
        if (mb_strlen($password) < 8) {
            return array(
                'ok'      => false,
                'message' => 'กรุณากำหนดรหัสผ่านสำหรับบัญชีตัวอย่าง อย่างน้อย 8 ตัวอักษร',
            );
        }

        $existing = $this->repo->one(
            'SELECT id FROM `{p}schools` WHERE name = ?',
            array('วิทยาลัยเทคนิคเพชรบูรณ์')
        );
        if ($existing !== null) {
            return array('ok' => false, 'message' => 'มีข้อมูลตัวอย่างอยู่แล้ว ไม่ได้สร้างซ้ำ');
        }

        $year = $this->repo->surveyYear();
        $gradYear = $year - 1;

        $schoolId = $this->repo->createSchool(array(
            'code'          => 'PTC',
            'name'          => 'วิทยาลัยเทคนิคเพชรบูรณ์',
            'province'      => 'เพชรบูรณ์',
            'affiliation'   => 'สอศ.',
            'contact_name'  => 'นางสาวปิยะดา รักเรียน',
            'contact_phone' => '056-711-xxx',
            'contact_email' => 'contact@petchtech.demo',
            'status'        => 'active',
        ));

        // A couple more institutions so the central admin screen is not empty.
        $this->repo->createSchool(array(
            'name' => 'วิทยาลัยอาชีวศึกษาเชียงใหม่', 'province' => 'เชียงใหม่',
            'affiliation' => 'สอศ.', 'contact_email' => 'contact@cmvc.demo',
            'contact_name' => 'ฝ่ายทะเบียน', 'status' => 'active',
        ));
        $this->repo->createSchool(array(
            'name' => 'วิทยาลัยการอาชีพนครสวรรค์', 'province' => 'นครสวรรค์',
            'affiliation' => 'สอศ.', 'contact_email' => 'contact@nsic.demo',
            'contact_name' => 'ฝ่ายวิชาการ', 'status' => 'pending',
        ));

        $departmentNames = array('ช่างยนต์', 'ช่างไฟฟ้า', 'การบัญชี', 'คอมพิวเตอร์ธุรกิจ', 'ช่างก่อสร้าง');
        $departments = array();
        $order = 0;
        foreach ($departmentNames as $name) {
            $departments[] = $this->repo->createDepartment($schoolId, $name, '', $order);
            $order++;
        }

        $advisorId = $this->repo->createUser(array(
            'school_id' => $schoolId, 'department_id' => $departments[0], 'role' => 'advisor',
            'email' => 'advisor@petchtech.demo', 'password' => $password,
            'full_name' => 'นางสาวปิยะดา รักเรียน', 'status' => 'active',
        ));
        $this->repo->createUser(array(
            'school_id' => $schoolId, 'role' => 'exec',
            'email' => 'exec@petchtech.demo', 'password' => $password,
            'full_name' => 'นางวราภรณ์ สุขใจ', 'status' => 'active',
        ));
        $this->repo->createUser(array(
            'school_id' => $schoolId, 'role' => 'schooladmin',
            'email' => 'admin@petchtech.demo', 'password' => $password,
            'full_name' => 'นายสมชาย ภักดี', 'status' => 'active',
        ));

        $firstNames = $this->firstNames();
        $lastNames = $this->lastNames();
        $companies = $this->companies();
        $universities = $this->universities();
        $statusPool = $this->statusPool();

        $alumniCount = 0;
        $statusCount = 0;

        for ($i = 1; $i <= 60; $i++) {
            $deptIndex = ($i - 1) % count($departments);
            $studentCode = '62' . str_pad((string) (31010000 + $i), 8, '0', STR_PAD_LEFT);
            // Predictable demo credentials: the code doubles as the ID digits.
            $nationalId = '1' . str_pad((string) (100000000000 + $i), 12, '0', STR_PAD_LEFT);
            $nationalId = substr($nationalId, 0, 13);

            $alumniId = $this->repo->createAlumni(array(
                'school_id'       => $schoolId,
                'department_id'   => $departments[$deptIndex],
                'advisor_user_id' => $deptIndex === 0 ? $advisorId : null,
                'student_code'    => $studentCode,
                'national_id'     => $nationalId,
                'title'           => ($i % 2 === 0) ? 'น.ส.' : 'นาย',
                'first_name'      => $firstNames[($i - 1) % count($firstNames)],
                'last_name'       => $lastNames[($i - 1) % count($lastNames)],
                'level'           => 'ปวส.',
                'graduation_year' => $gradYear,
                'phone'           => '08' . str_pad((string) (10000000 + $i * 137), 8, '0', STR_PAD_LEFT),
                'email'           => 'alumni' . $i . '@example.demo',
            ));
            $alumniCount++;

            // Leave roughly one in six unanswered, so the "pending" states on
            // the advisor and dashboard screens are not empty.
            if ($i % 6 === 0) {
                continue;
            }

            $status = $statusPool[($i * 7) % count($statusPool)];
            $data = array(
                'employment_status' => $status,
                'company_name' => '', 'job_position' => '', 'salary' => '',
                'work_province' => '', 'study_place' => '', 'study_level' => '',
                'study_major' => '', 'note' => null,
            );

            if ($status === 'employed_match' || $status === 'employed_other' || $status === 'freelance') {
                $data['company_name']  = $companies[($i - 1) % count($companies)];
                $data['job_position']  = $status === 'freelance' ? 'เจ้าของกิจการ' : 'ช่างเทคนิค';
                $data['salary']        = 12000 + (($i * 373) % 14000);
                $data['work_province'] = ($i % 3 === 0) ? 'กรุงเทพมหานคร' : 'เพชรบูรณ์';
            } elseif ($status === 'study') {
                $data['study_place'] = $universities[($i - 1) % count($universities)];
                $data['study_level'] = 'ปริญญาตรี';
                $data['study_major'] = $departmentNames[$deptIndex];
            } else {
                $data['note'] = $status === 'military'
                    ? 'อยู่ระหว่างรับราชการทหาร'
                    : 'กำลังหางานในสายงานที่เรียนมา';
            }

            $this->repo->saveStatus(
                $alumniId, $schoolId, $year, $data,
                ($i % 11 === 0), // a few left as drafts
                'alumni', $alumniId
            );
            $statusCount++;
        }

        // A second, earlier year so the comparison screen has two bars.
        $previousYear = $gradYear - 1;
        for ($i = 1; $i <= 20; $i++) {
            $deptIndex = ($i - 1) % count($departments);
            $alumniId = $this->repo->createAlumni(array(
                'school_id'       => $schoolId,
                'department_id'   => $departments[$deptIndex],
                'student_code'    => '61' . str_pad((string) (31010000 + $i), 8, '0', STR_PAD_LEFT),
                'national_id'     => substr('1' . str_pad((string) (200000000000 + $i), 12, '0', STR_PAD_LEFT), 0, 13),
                'title'           => ($i % 2 === 0) ? 'น.ส.' : 'นาย',
                'first_name'      => $firstNames[($i + 3) % count($firstNames)],
                'last_name'       => $lastNames[($i + 5) % count($lastNames)],
                'level'           => 'ปวส.',
                'graduation_year' => $previousYear,
            ));
            $alumniCount++;

            $status = $statusPool[($i * 3) % count($statusPool)];
            $this->repo->saveStatus(
                $alumniId, $schoolId, $year - 1,
                array(
                    'employment_status' => $status,
                    'company_name' => $companies[($i - 1) % count($companies)],
                    'job_position' => 'ช่างเทคนิค', 'salary' => 13000 + (($i * 211) % 9000),
                    'work_province' => 'เพชรบูรณ์', 'study_place' => '', 'study_level' => '',
                    'study_major' => '', 'note' => null,
                ),
                false, 'alumni', $alumniId
            );
            $statusCount++;
        }

        return array(
            'ok'      => true,
            'message' => 'สร้างข้อมูลตัวอย่าง: สถานศึกษา 3 แห่ง, ผู้สำเร็จการศึกษา ' . $alumniCount
                . ' คน, คำตอบแบบสำรวจ ' . $statusCount . ' รายการ',
            // The password is deliberately not repeated here: it is the one
            // just typed into the installer, and echoing it back would put it
            // into the page, the browser history and any proxy log.
            'accounts' => array(
                'advisor@petchtech.demo (ครูที่ปรึกษา)',
                'exec@petchtech.demo (ผู้บริหาร)',
                'admin@petchtech.demo (ผู้ดูแลสถานศึกษา)',
                'ทั้งสามบัญชีใช้รหัสผ่านที่คุณกำหนดไว้',
                'ผู้สำเร็จการศึกษา: รหัส 6231010001 / เลขบัตร 1100000000001',
            ),
        );
    }

    // ---------------------------------------------------------- demo namespace

    /**
     * What the demo namespace holds right now.
     *
     * @return array
     */
    public function demoSummary()
    {
        $ids = $this->demoSchoolIds();
        $summary = array(
            'present' => count($ids) > 0, 'schools' => count($ids),
            'users' => 0, 'graduates' => 0, 'students' => 0, 'answers' => 0,
        );
        if (!$ids) {
            return $summary;
        }

        $in = $this->placeholders($ids);
        $summary['users'] = (int) $this->repo->scalar(
            'SELECT COUNT(*) FROM `{p}users` WHERE school_id IN (' . $in . ') OR email LIKE ?',
            array_merge($ids, array('%@' . self::DEMO_DOMAIN))
        );
        $summary['graduates'] = (int) $this->repo->scalar(
            'SELECT COUNT(*) FROM `{p}alumni` WHERE school_id IN (' . $in . ') AND study_state = ?',
            array_merge($ids, array('graduated'))
        );
        $summary['students'] = (int) $this->repo->scalar(
            'SELECT COUNT(*) FROM `{p}alumni` WHERE school_id IN (' . $in . ') AND study_state = ?',
            array_merge($ids, array('studying'))
        );
        $summary['answers'] = (int) $this->repo->scalar(
            'SELECT COUNT(*) FROM `{p}alumni_status` WHERE school_id IN (' . $in . ')',
            $ids
        );
        return $summary;
    }

    /**
     * How much data the installation carries outside the demo namespace.
     *
     * `production` being true is what makes the settings screen ask for a
     * typed confirmation before seeding.
     *
     * @return array
     */
    public function realScale()
    {
        $ids = $this->demoSchoolIds();

        if ($ids) {
            $in = $this->placeholders($ids);
            $alumni = (int) $this->repo->scalar(
                'SELECT COUNT(*) FROM `{p}alumni` WHERE school_id NOT IN (' . $in . ')',
                $ids
            );
            $users = (int) $this->repo->scalar(
                'SELECT COUNT(*) FROM `{p}users`'
                . ' WHERE (school_id IS NULL OR school_id NOT IN (' . $in . '))'
                . '   AND email NOT LIKE ?',
                array_merge($ids, array('%@' . self::DEMO_DOMAIN))
            );
        } else {
            $alumni = (int) $this->repo->scalar('SELECT COUNT(*) FROM `{p}alumni`');
            $users = (int) $this->repo->scalar('SELECT COUNT(*) FROM `{p}users`');
        }

        return array(
            'alumni'     => $alumni,
            'users'      => $users,
            'production' => $alumni >= self::REAL_DATA_THRESHOLD,
        );
    }

    /**
     * Builds one institution's worth of demo data, covering every role and
     * every screen the user manual illustrates.
     *
     * The account password is generated here rather than fixed in the source,
     * so that two installations never share one, and returned to the caller to
     * be shown once. These accounts exist only alongside data that is labelled
     * as a sample and can be deleted in one step.
     *
     * @return array
     */
    public function seedDemo()
    {
        if ($this->demoSchoolIds()) {
            return array(
                'ok' => false,
                'message' => 'มีข้อมูลตัวอย่างอยู่แล้ว — ลบชุดเดิมก่อนจึงจะสร้างใหม่ได้',
            );
        }

        $password = $this->makePassword();
        $year = $this->repo->surveyYear();
        $gradYear = $year - 1;
        $mark = ' (ข้อมูลทดสอบ)';

        $schoolId = $this->repo->createSchool(array(
            'code'          => self::DEMO_CODE_PREFIX . '01',
            'name'          => 'วิทยาลัยเทคนิคตัวอย่าง' . $mark,
            'province'      => 'เพชรบูรณ์',
            'affiliation'   => 'สอศ.',
            'contact_name'  => 'ผู้ประสานงานตัวอย่าง',
            'contact_phone' => '000-000-0000',
            'contact_email' => 'contact@' . self::DEMO_DOMAIN,
            'status'        => 'active',
        ));
        $schoolIds = array($schoolId);

        // A second active institution, so the central admin listing is not a
        // single row, and a third still pending, so the approval queue and its
        // sidebar badge have something to show.
        $schoolIds[] = $this->repo->createSchool(array(
            'code' => self::DEMO_CODE_PREFIX . '02',
            'name' => 'วิทยาลัยอาชีวศึกษาตัวอย่าง' . $mark,
            'province' => 'เชียงใหม่', 'affiliation' => 'สอศ.',
            'contact_name' => 'ฝ่ายทะเบียนตัวอย่าง',
            'contact_email' => 'contact2@' . self::DEMO_DOMAIN, 'status' => 'active',
        ));
        $schoolIds[] = $this->repo->createSchool(array(
            'code' => self::DEMO_CODE_PREFIX . '03',
            'name' => 'วิทยาลัยการอาชีพตัวอย่าง' . $mark,
            'province' => 'นครสวรรค์', 'affiliation' => 'สอศ.',
            'contact_name' => 'ฝ่ายวิชาการตัวอย่าง',
            'contact_email' => 'contact3@' . self::DEMO_DOMAIN, 'status' => 'pending',
        ));

        // Recorded before anything else is attached to them: if a later step
        // fails, the purge still knows what to clean up.
        $this->rememberDemoSchools($schoolIds);

        $departmentNames = array('ช่างยนต์', 'ช่างไฟฟ้า', 'การบัญชี', 'คอมพิวเตอร์ธุรกิจ', 'ช่างก่อสร้าง');
        $departments = array();
        $order = 0;
        foreach ($departmentNames as $name) {
            $departments[] = $this->repo->createDepartment($schoolId, $name, '', $order);
            $order++;
        }

        $advisorIdcard = '1000000000001';
        $advisorId = $this->repo->createUser(array(
            'school_id' => $schoolId, 'department_id' => $departments[0], 'role' => 'advisor',
            'email' => 'advisor@' . self::DEMO_DOMAIN, 'username' => $advisorIdcard,
            'password' => $password, 'full_name' => 'ครูที่ปรึกษาตัวอย่าง', 'status' => 'active',
        ));
        $this->repo->createUser(array(
            'school_id' => $schoolId, 'role' => 'exec',
            'email' => 'exec@' . self::DEMO_DOMAIN, 'password' => $password,
            'full_name' => 'ผู้บริหารตัวอย่าง', 'status' => 'active',
        ));
        $this->repo->createUser(array(
            'school_id' => $schoolId, 'role' => 'schooladmin',
            'email' => 'schooladmin@' . self::DEMO_DOMAIN, 'password' => $password,
            'full_name' => 'ผู้ดูแลสถานศึกษาตัวอย่าง', 'status' => 'active',
        ));
        // Belongs to no institution, exactly like the real central admin.
        $this->repo->createUser(array(
            'school_id' => null, 'role' => 'centraladmin',
            'email' => 'central@' . self::DEMO_DOMAIN, 'password' => $password,
            'full_name' => 'ผู้ดูแลระบบกลางตัวอย่าง', 'status' => 'active',
        ));
        // One more advisor left pending, so the user listing shows a status
        // other than "active".
        $this->repo->createUser(array(
            'school_id' => $schoolId, 'department_id' => $departments[1], 'role' => 'advisor',
            'email' => 'advisor2@' . self::DEMO_DOMAIN, 'password' => $password,
            'full_name' => 'ครูที่ปรึกษาตัวอย่าง (รออนุมัติ)', 'status' => 'pending',
        ));

        $counts = $this->fillPeople($schoolId, $departments, $departmentNames,
            $advisorId, $advisorIdcard, $year, $gradYear);

        $this->repo->setSetting('demo_seeded_at', date('Y-m-d H:i:s'));

        return array(
            'ok'       => true,
            'password' => $password,
            'message'  => 'สร้างข้อมูลตัวอย่างเรียบร้อย — สถานศึกษา ' . count($schoolIds) . ' แห่ง,'
                . ' ผู้สำเร็จการศึกษา ' . $counts['graduates'] . ' คน,'
                . ' นักศึกษาปัจจุบัน ' . $counts['students'] . ' คน,'
                . ' คำตอบแบบสำรวจ ' . $counts['answers'] . ' รายการ',
            'staff' => array(
                array('role' => 'ผู้ดูแลระบบกลาง', 'login' => 'central@' . self::DEMO_DOMAIN),
                array('role' => 'ผู้ดูแลสถานศึกษา', 'login' => 'schooladmin@' . self::DEMO_DOMAIN),
                array('role' => 'ผู้บริหาร', 'login' => 'exec@' . self::DEMO_DOMAIN),
                array('role' => 'ครูที่ปรึกษา', 'login' => 'advisor@' . self::DEMO_DOMAIN),
            ),
            'learners' => array(
                array('role' => 'นักศึกษาปัจจุบัน', 'code' => $counts['student_code'],
                      'idcard' => $counts['student_idcard']),
                array('role' => 'ผู้สำเร็จการศึกษา', 'code' => $counts['graduate_code'],
                      'idcard' => $counts['graduate_idcard']),
            ),
        );
    }

    /**
     * Removes everything seedDemo() created, and nothing else.
     *
     * Every statement is scoped to institutions that passed the two-marker
     * check in demoSchoolIds(), or to the reserved mail domain. There is no
     * unscoped DELETE here, so a wrong id in the setting cannot widen the
     * blast radius beyond the rows this class created.
     *
     * @param int $keepUserId the signed-in account, never deleted even when it
     *                        is itself a demo account
     * @return array
     */
    public function purgeDemo($keepUserId = 0)
    {
        $ids = $this->demoSchoolIds();
        if (!$ids) {
            return array('ok' => false, 'message' => 'ไม่พบข้อมูลตัวอย่างในระบบ');
        }

        $keepUserId = (int) $keepUserId;
        $in = $this->placeholders($ids);
        $removed = array();

        $removed['answers'] = $this->repo->run(
            'DELETE FROM `{p}alumni_status` WHERE school_id IN (' . $in . ')', $ids
        )->rowCount();
        $removed['alumni'] = $this->repo->run(
            'DELETE FROM `{p}alumni` WHERE school_id IN (' . $in . ')', $ids
        )->rowCount();
        $removed['groups'] = $this->repo->run(
            'DELETE FROM `{p}student_groups` WHERE school_id IN (' . $in . ')', $ids
        )->rowCount();
        $removed['departments'] = $this->repo->run(
            'DELETE FROM `{p}departments` WHERE school_id IN (' . $in . ')', $ids
        )->rowCount();

        $userSql = 'DELETE FROM `{p}users`'
            . ' WHERE (school_id IN (' . $in . ') OR email LIKE ?)';
        $userParams = array_merge($ids, array('%@' . self::DEMO_DOMAIN));
        if ($keepUserId > 0) {
            $userSql .= ' AND id <> ?';
            $userParams[] = $keepUserId;
        }
        $removed['users'] = $this->repo->run($userSql, $userParams)->rowCount();

        $this->repo->run('DELETE FROM `{p}audit_log` WHERE school_id IN (' . $in . ')', $ids);

        $removed['schools'] = $this->repo->run(
            'DELETE FROM `{p}schools` WHERE id IN (' . $in . ')', $ids
        )->rowCount();

        $this->repo->setSetting(self::DEMO_SETTING, '');
        $this->repo->setSetting('demo_seeded_at', '');

        $message = 'ลบข้อมูลตัวอย่างแล้ว — สถานศึกษา ' . $removed['schools'] . ' แห่ง,'
            . ' ผู้ใช้งาน ' . $removed['users'] . ' บัญชี,'
            . ' ผู้เรียน ' . $removed['alumni'] . ' คน,'
            . ' คำตอบแบบสำรวจ ' . $removed['answers'] . ' รายการ';

        return array('ok' => true, 'message' => $message, 'removed' => $removed);
    }

    /**
     * The demo institutions, as ids that passed both markers.
     *
     * @return array list of int
     */
    private function demoSchoolIds()
    {
        $raw = (string) $this->repo->setting(self::DEMO_SETTING, '');
        if (trim($raw) === '') {
            return array();
        }

        $candidates = array();
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $candidates[] = $id;
            }
        }
        if (!$candidates) {
            return array();
        }

        // Second marker. An id whose institution is missing, or whose code
        // does not carry the demo prefix, is dropped rather than trusted.
        $rows = $this->repo->all(
            'SELECT id FROM `{p}schools`'
            . ' WHERE id IN (' . $this->placeholders($candidates) . ') AND code LIKE ?',
            array_merge($candidates, array(self::DEMO_CODE_PREFIX . '%'))
        );

        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }
        return $ids;
    }

    /**
     * @param array $ids
     */
    private function rememberDemoSchools($ids)
    {
        $clean = array();
        foreach ($ids as $id) {
            $clean[] = (int) $id;
        }
        $this->repo->setSetting(self::DEMO_SETTING, implode(',', $clean));
    }

    /**
     * `?, ?, ?` for an IN clause — the list is built from integers this class
     * produced, and still bound rather than interpolated.
     *
     * @param array $values
     * @return string
     */
    private function placeholders($values)
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    /**
     * Learners for the demo institution: graduates who answer the survey, and
     * current students who have not reached it yet.
     *
     * @return array counts, plus one sample login of each kind
     */
    private function fillPeople($schoolId, $departments, $departmentNames,
                                $advisorId, $advisorIdcard, $year, $gradYear)
    {
        $firstNames = $this->firstNames();
        $lastNames = $this->lastNames();
        $companies = $this->companies();
        $universities = $this->universities();
        $statusPool = $this->statusPool();
        $plans = array_keys(graduation_plans());

        $out = array('graduates' => 0, 'students' => 0, 'answers' => 0,
            'graduate_code' => '', 'graduate_idcard' => '',
            'student_code' => '', 'student_idcard' => '');

        // ---- graduates, the cohort the employment survey is about
        for ($i = 1; $i <= 48; $i++) {
            $deptIndex = ($i - 1) % count($departments);
            $code = '65' . str_pad((string) (99000000 + $i), 8, '0', STR_PAD_LEFT);
            $idcard = substr('9' . str_pad((string) (100000000000 + $i), 12, '0', STR_PAD_LEFT), 0, 13);

            $alumniId = $this->repo->createAlumni(array(
                'school_id'       => $schoolId,
                'department_id'   => $departments[$deptIndex],
                'advisor_user_id' => $deptIndex === 0 ? $advisorId : null,
                'student_code'    => $code,
                'national_id'     => $idcard,
                'title'           => ($i % 2 === 0) ? 'น.ส.' : 'นาย',
                'first_name'      => $firstNames[($i - 1) % count($firstNames)],
                'last_name'       => $lastNames[($i - 1) % count($lastNames)],
                'level'           => 'ปวส.',
                'graduation_year' => $gradYear,
                'study_state'     => 'graduated',
                'phone'           => '08' . str_pad((string) (10000000 + $i * 137), 8, '0', STR_PAD_LEFT),
                'email'           => 'graduate' . $i . '@' . self::DEMO_DOMAIN,
            ));
            $out['graduates']++;
            if ($out['graduate_code'] === '') {
                $out['graduate_code'] = $code;
                $out['graduate_idcard'] = $idcard;
            }

            // Roughly one in six left unanswered, so "pending" is not empty on
            // the advisor screen and the collection rate is not a flat 100%.
            if ($i % 6 === 0) {
                continue;
            }

            $status = $statusPool[($i * 7) % count($statusPool)];
            $data = array(
                'employment_status' => $status,
                'company_name' => '', 'job_position' => '', 'salary' => '',
                'work_province' => '', 'study_place' => '', 'study_level' => '',
                'study_major' => '', 'note' => null,
            );
            if ($status === 'employed_match' || $status === 'employed_other' || $status === 'freelance') {
                $data['company_name']  = $companies[($i - 1) % count($companies)];
                $data['job_position']  = $status === 'freelance' ? 'เจ้าของกิจการ' : 'ช่างเทคนิค';
                $data['salary']        = 12000 + (($i * 373) % 14000);
                $data['work_province'] = ($i % 3 === 0) ? 'กรุงเทพมหานคร' : 'เพชรบูรณ์';
            } elseif ($status === 'study') {
                $data['study_place'] = $universities[($i - 1) % count($universities)];
                $data['study_level'] = 'ปริญญาตรี';
                $data['study_major'] = $departmentNames[$deptIndex];
            } else {
                $data['note'] = $status === 'military'
                    ? 'อยู่ระหว่างรับราชการทหาร'
                    : 'กำลังหางานในสายงานที่เรียนมา';
            }

            $this->repo->saveStatus(
                $alumniId, $schoolId, $year, $data, ($i % 11 === 0), 'alumni', $alumniId
            );
            $out['answers']++;
        }

        // ---- an earlier survey year, so the comparison screen has two bars
        for ($i = 1; $i <= 20; $i++) {
            $deptIndex = ($i - 1) % count($departments);
            $alumniId = $this->repo->createAlumni(array(
                'school_id'       => $schoolId,
                'department_id'   => $departments[$deptIndex],
                'student_code'    => '64' . str_pad((string) (99000000 + $i), 8, '0', STR_PAD_LEFT),
                'national_id'     => substr('9' . str_pad((string) (200000000000 + $i), 12, '0', STR_PAD_LEFT), 0, 13),
                'title'           => ($i % 2 === 0) ? 'น.ส.' : 'นาย',
                'first_name'      => $firstNames[($i + 3) % count($firstNames)],
                'last_name'       => $lastNames[($i + 5) % count($lastNames)],
                'level'           => 'ปวส.',
                'graduation_year' => $gradYear - 1,
                'study_state'     => 'graduated',
            ));
            $out['graduates']++;

            $status = $statusPool[($i * 3) % count($statusPool)];
            $this->repo->saveStatus(
                $alumniId, $schoolId, $year - 1,
                array(
                    'employment_status' => $status,
                    'company_name' => $companies[($i - 1) % count($companies)],
                    'job_position' => 'ช่างเทคนิค', 'salary' => 13000 + (($i * 211) % 9000),
                    'work_province' => 'เพชรบูรณ์', 'study_place' => '', 'study_level' => '',
                    'study_major' => '', 'note' => null,
                ),
                false, 'alumni', $alumniId
            );
            $out['answers']++;
        }

        // ---- current students, who answer the intention question instead
        $groupCodes = array('ปวส.2/1 ช่างยนต์', 'ปวส.2/2 ช่างไฟฟ้า');
        foreach ($groupCodes as $index => $groupName) {
            $this->repo->upsertStudentGroup(array(
                'school_id'       => $schoolId,
                'academic_year'   => $year,
                'semester'        => 1,
                'group_code'      => 'DEMOG' . ($index + 1),
                'grade'           => 'ปวส.2',
                'group_name'      => $groupName,
                'group_abbr'      => 'DEMOG' . ($index + 1),
                'teacher_idcard'  => $advisorIdcard,
                'teacher_name'    => 'ครูที่ปรึกษาตัวอย่าง',
                'advisor_user_id' => $advisorId,
                'external_source' => 'demo',
            ));
        }

        for ($i = 1; $i <= 24; $i++) {
            $deptIndex = ($i - 1) % count($departments);
            $code = '67' . str_pad((string) (99000000 + $i), 8, '0', STR_PAD_LEFT);
            $idcard = substr('9' . str_pad((string) (300000000000 + $i), 12, '0', STR_PAD_LEFT), 0, 13);

            $studentId = $this->repo->createAlumni(array(
                'school_id'       => $schoolId,
                'department_id'   => $departments[$deptIndex],
                'advisor_user_id' => $advisorId,
                'student_code'    => $code,
                'national_id'     => $idcard,
                'title'           => ($i % 2 === 0) ? 'น.ส.' : 'นาย',
                'first_name'      => $firstNames[($i + 1) % count($firstNames)],
                'last_name'       => $lastNames[($i + 2) % count($lastNames)],
                'level'           => 'ปวส.',
                'graduation_year' => $year,
                'study_state'     => 'studying',
                'phone'           => '09' . str_pad((string) (20000000 + $i * 211), 8, '0', STR_PAD_LEFT),
                'email'           => 'student' . $i . '@' . self::DEMO_DOMAIN,
            ));
            $out['students']++;
            if ($out['student_code'] === '') {
                $out['student_code'] = $code;
                $out['student_idcard'] = $idcard;
            }

            // A quarter of them have not said yet, so that screen has both
            // an answered and an unanswered state to show.
            if ($i % 4 !== 0) {
                $this->repo->updateAlumniPlan($studentId, $plans[($i - 1) % count($plans)], '');
            }
        }

        return $out;
    }

    /**
     * A password for the sample accounts, generated per installation.
     *
     * Ambiguous characters are left out of the alphabet: this is read off a
     * screen and typed back in, sometimes from a printed manual.
     *
     * @return string
     */
    private function makePassword()
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $raw = vec_random_bytes(12);
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[ord($raw[$i]) % 32];
        }
        return 'demo-' . $out;
    }

    /** @return array */
    private function firstNames()
    {
        return array('กิตติพงศ์', 'ศิริพร', 'ธนากร', 'กนกวรรณ', 'อนุชา', 'วีระ', 'สุดารัตน์',
            'ณัฐพล', 'พรทิพย์', 'ชัยวัฒน์', 'มาลี', 'สมศักดิ์', 'อรวรรณ', 'ปกรณ์', 'ญาดา');
    }

    /** @return array */
    private function lastNames()
    {
        return array('ใจดี', 'มั่นคง', 'แสงทอง', 'ดีเลิศ', 'ผาสุข', 'ทองคำ', 'ศรีสุข',
            'บุญมี', 'รุ่งเรือง', 'พัฒนา');
    }

    /** @return array */
    private function companies()
    {
        return array('บริษัท ไทยออโต้ จำกัด', 'อู่ช่างเล็ก', 'บริษัท พลังไฟฟ้า จำกัด',
            'ห้างหุ้นส่วน บัญชีดี', 'บริษัท ซอฟต์แวร์ไทย จำกัด', 'บริษัท ก่อสร้างมั่นคง จำกัด');
    }

    /** @return array */
    private function universities()
    {
        return array('มหาวิทยาลัยเทคโนโลยีราชมงคลล้านนา', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์',
            'สถาบันการอาชีวศึกษาภาคเหนือ');
    }

    /**
     * Weighted so the dashboard shows a believable spread rather than an
     * even split across every status.
     *
     * @return array
     */
    private function statusPool()
    {
        return array(
            'employed_match', 'employed_match', 'employed_match', 'employed_match',
            'employed_other', 'employed_other', 'freelance',
            'study', 'study', 'unemployed', 'military',
        );
    }
}
