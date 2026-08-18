<?php
/**
 * Optional demo data, so a fresh install has something to look at on every
 * screen. Never runs automatically — the installer offers it as a choice.
 */
class Seeder
{
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

        $firstNames = array('กิตติพงศ์', 'ศิริพร', 'ธนากร', 'กนกวรรณ', 'อนุชา', 'วีระ', 'สุดารัตน์',
            'ณัฐพล', 'พรทิพย์', 'ชัยวัฒน์', 'มาลี', 'สมศักดิ์', 'อรวรรณ', 'ปกรณ์', 'ญาดา');
        $lastNames = array('ใจดี', 'มั่นคง', 'แสงทอง', 'ดีเลิศ', 'ผาสุข', 'ทองคำ', 'ศรีสุข',
            'บุญมี', 'รุ่งเรือง', 'พัฒนา');
        $companies = array('บริษัท ไทยออโต้ จำกัด', 'อู่ช่างเล็ก', 'บริษัท พลังไฟฟ้า จำกัด',
            'ห้างหุ้นส่วน บัญชีดี', 'บริษัท ซอฟต์แวร์ไทย จำกัด', 'บริษัท ก่อสร้างมั่นคง จำกัด');
        $universities = array('มหาวิทยาลัยเทคโนโลยีราชมงคลล้านนา', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์',
            'สถาบันการอาชีวศึกษาภาคเหนือ');
        $statusPool = array(
            'employed_match', 'employed_match', 'employed_match', 'employed_match',
            'employed_other', 'employed_other', 'freelance',
            'study', 'study', 'unemployed', 'military',
        );

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
            'message' => 'สร้างข้อมูลตัวอย่าง: สถานศึกษา 3 แห่ง, ศิษย์เก่า ' . $alumniCount
                . ' คน, คำตอบแบบสำรวจ ' . $statusCount . ' รายการ',
            // The password is deliberately not repeated here: it is the one
            // just typed into the installer, and echoing it back would put it
            // into the page, the browser history and any proxy log.
            'accounts' => array(
                'advisor@petchtech.demo (ครูที่ปรึกษา)',
                'exec@petchtech.demo (ผู้บริหาร)',
                'admin@petchtech.demo (ผู้ดูแลสถานศึกษา)',
                'ทั้งสามบัญชีใช้รหัสผ่านที่คุณกำหนดไว้',
                'ศิษย์เก่า: รหัส 6231010001 / เลขบัตร 1100000000001',
            ),
        );
    }
}
