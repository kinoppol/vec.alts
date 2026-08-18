<?php
/**
 * The alumnus' own view: fill in this year's survey, review past years.
 */
class AlumniController extends Controller
{
    public function form()
    {
        $this->auth->require_role('alumni');

        $alumni = $this->repo->alumni($this->auth->id());
        if ($alumni === null) {
            flash('error', 'ไม่พบข้อมูลศิษย์เก่าของบัญชีนี้');
            $this->auth->logout();
            redirect('login');
        }

        $year = $this->repo->surveyYear();
        $allowSelfUpdate = $this->repo->setting('allow_self_update', '1') === '1';

        if (is_post()) {
            csrf_verify();

            if (!$allowSelfUpdate) {
                flash('error', 'ขณะนี้ระบบปิดการแก้ไขข้อมูลด้วยตนเอง กรุณาติดต่อครูที่ปรึกษา');
                redirect('alumni');
            }

            $isDraft = post('action') === 'draft';
            $status = post('employment_status');

            if (!$isDraft && !array_key_exists($status, employment_statuses())) {
                flash('error', 'กรุณาเลือกสถานะปัจจุบันก่อนส่งข้อมูล');
                redirect('alumni');
            }

            $this->repo->updateAlumniContact($alumni['id'], array(
                'phone'   => post('phone'),
                'email'   => post('email'),
                'line_id' => post('line_id'),
                'address' => post('address'),
            ));

            $this->repo->saveStatus(
                $alumni['id'],
                $alumni['school_id'],
                $year,
                $this->collectStatusInput(),
                $isDraft,
                'alumni',
                $this->auth->id()
            );

            $this->repo->audit(
                $isDraft ? 'survey.draft' : 'survey.submit',
                $alumni['student_code'],
                'ปีสำรวจ ' . $year,
                $this->actor()
            );

            flash('success', $isDraft
                ? 'บันทึกร่างเรียบร้อยแล้ว คุณกลับมาแก้ไขได้ภายหลัง'
                : 'ส่งข้อมูลเรียบร้อยแล้ว ขอบคุณที่ช่วยพัฒนาการเรียนการสอน');
            redirect('alumni');
        }

        if (!$allowSelfUpdate) {
            flash('info', 'ขณะนี้ระบบเปิดให้เฉพาะครูที่ปรึกษาเป็นผู้กรอกข้อมูล');
        }

        $this->render('alumni/form', array(
            'title'    => 'ข้อมูลของฉัน',
            'alumni'   => $alumni,
            'status'   => $this->repo->statusFor($alumni['id'], $year),
            'year'     => $year,
            'onBehalf' => false,
        ));
    }

    public function history()
    {
        $this->auth->require_role('alumni');

        $alumni = $this->repo->alumni($this->auth->id());
        if ($alumni === null) {
            redirect('alumni');
        }

        $this->render('alumni/history', array(
            'title'   => 'ประวัติการอัปเดต',
            'alumni'  => $alumni,
            'history' => $this->repo->statusHistory($alumni['id']),
        ));
    }

    /**
     * Pulls the survey fields out of $_POST, blanking the branches that do not
     * apply so a change of status does not leave stale answers behind.
     *
     * @return array
     */
    public function collectStatusInput()
    {
        $statuses = employment_statuses();
        $code = post('employment_status');
        $group = isset($statuses[$code]) ? $statuses[$code]['group'] : '';

        $data = array(
            'employment_status' => isset($statuses[$code]) ? $code : '',
            'company_name'  => '',
            'job_position'  => '',
            'salary'        => '',
            'work_province' => '',
            'study_place'   => '',
            'study_level'   => '',
            'study_major'   => '',
            'note'          => null,
        );

        if ($group === 'job') {
            $data['company_name']  = post('company_name');
            $data['job_position']  = post('job_position');
            $data['salary']        = post('salary');
            $data['work_province'] = post('work_province');
        } elseif ($group === 'study') {
            $data['study_place'] = post('study_place');
            $data['study_level'] = post('study_level');
            $data['study_major'] = post('study_major');
        } elseif ($group === 'note') {
            $data['note'] = post('note');
        }

        return $data;
    }
}
