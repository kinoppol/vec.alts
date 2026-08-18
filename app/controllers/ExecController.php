<?php
/**
 * Executive reporting: dashboard, per-department, year-on-year, CSV export.
 */
class ExecController extends Controller
{
    public function dashboard()
    {
        $this->auth->require_role(array('exec', 'schooladmin'));

        $schoolId = $this->auth->schoolId();
        $year = $this->repo->surveyYear();
        $gradYear = query_int('grad_year', 0);

        $this->render('exec/dashboard', array(
            'title'       => 'แดชบอร์ดผู้บริหาร',
            'summary'     => $this->repo->schoolSummary($schoolId, $year, $gradYear),
            'departments' => $this->repo->departmentBreakdown($schoolId, $year, $gradYear),
            'school'      => $this->currentSchool(),
            'year'        => $year,
            'gradYear'    => $gradYear,
            'gradYears'   => $this->repo->graduationYears($schoolId),
        ));
    }

    public function departments()
    {
        $this->auth->require_role(array('exec', 'schooladmin'));

        $year = $this->repo->surveyYear();
        $this->render('exec/departments', array(
            'title'       => 'รายงานตามแผนก',
            'departments' => $this->repo->departmentBreakdown($this->auth->schoolId(), $year, 0),
            'year'        => $year,
        ));
    }

    public function years()
    {
        $this->auth->require_role(array('exec', 'schooladmin'));

        $this->render('exec/years', array(
            'title' => 'เปรียบเทียบปีการศึกษา',
            'years' => $this->repo->yearComparison($this->auth->schoolId(), 6),
        ));
    }

    public function export()
    {
        $this->auth->require_role(array('exec', 'schooladmin'));

        $schoolId = $this->auth->schoolId();

        if (query('download') === '1') {
            $this->streamCsv($schoolId);
            return;
        }

        $this->render('exec/export', array(
            'title'     => 'ส่งออกรายงาน',
            'gradYears' => $this->repo->graduationYears($schoolId),
            'year'      => $this->repo->surveyYear(),
        ));
    }

    /**
     * @param int $schoolId
     */
    private function streamCsv($schoolId)
    {
        $surveyYear = query_int('survey_year', $this->repo->surveyYear());
        $gradYear = query_int('grad_year', 0);
        $scope = query('scope', 'answered');

        $where = 'a.school_id = ?';
        $params = array($surveyYear, $schoolId);
        if ($gradYear > 0) {
            $where .= ' AND a.graduation_year = ?';
            $params[] = $gradYear;
        }
        if ($scope === 'answered') {
            $where .= ' AND st.id IS NOT NULL AND st.is_draft = 0';
        }

        $rows = $this->repo->all(
            'SELECT a.student_code, a.title, a.first_name, a.last_name, a.level,'
            . ' a.graduation_year, a.phone, a.email, d.name AS department_name,'
            . ' st.employment_status, st.company_name, st.job_position, st.salary,'
            . ' st.work_province, st.study_place, st.study_level, st.study_major,'
            . ' st.note, st.submitted_at'
            . ' FROM `{p}alumni` a'
            . ' LEFT JOIN `{p}departments` d ON d.id = a.department_id'
            . ' LEFT JOIN `{p}alumni_status` st ON st.alumni_id = a.id AND st.survey_year = ?'
            . ' WHERE ' . $where
            . ' ORDER BY d.name ASC, a.student_code ASC',
            $params
        );

        $header = array(
            'รหัสนักศึกษา', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'สาขาวิชา', 'ระดับ', 'ปีที่สำเร็จการศึกษา',
            'เบอร์โทรศัพท์', 'อีเมล', 'สถานะ', 'สถานประกอบการ', 'ตำแหน่ง', 'เงินเดือน',
            'จังหวัดที่ทำงาน', 'สถานศึกษาที่ศึกษาต่อ', 'ระดับที่ศึกษาต่อ', 'สาขาที่ศึกษาต่อ',
            'หมายเหตุ', 'ส่งข้อมูลเมื่อ',
        );

        $data = array();
        foreach ($rows as $row) {
            $data[] = array(
                $row['student_code'],
                $row['title'],
                $row['first_name'],
                $row['last_name'],
                $row['department_name'] !== null ? $row['department_name'] : '',
                $row['level'],
                (int) $row['graduation_year'] > 0 ? $row['graduation_year'] : '',
                $row['phone'],
                $row['email'],
                $row['employment_status'] !== null ? employment_label($row['employment_status']) : 'ยังไม่ตอบ',
                $row['company_name'],
                $row['job_position'],
                $row['salary'] !== null ? (int) $row['salary'] : '',
                $row['work_province'],
                $row['study_place'],
                $row['study_level'],
                $row['study_major'],
                $row['note'],
                $row['submitted_at'],
            );
        }

        $this->repo->audit('report.export', 'CSV', count($data) . ' rows', $this->actor());

        $filename = 'alumni-' . $schoolId . '-' . $surveyYear
            . ($gradYear > 0 ? '-grad' . $gradYear : '') . '.csv';
        $this->csvDownload($filename, $header, $data);
    }
}
