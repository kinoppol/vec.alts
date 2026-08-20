<?php
/**
 * Advisor worklist and filling the survey in on an alumnus' behalf.
 */
class AdvisorController extends Controller
{
    const PER_PAGE = 25;

    public function index()
    {
        $this->auth->require_role('advisor');

        $schoolId = $this->auth->schoolId();
        $page = max(1, query_int('page', 1));

        // The caseload holds current students as well as graduates. Which of
        // them to show is the advisor's choice, not fixed here: an advisor
        // whose groups came from RMS has thousands of current students and
        // would otherwise be shown an empty screen.
        $studyState = query('study');
        $states = study_states();
        if (!isset($states[$studyState])) {
            $studyState = '';
        }

        $filters = array(
            'school_id'     => $schoolId,
            'advisor_id'    => $this->auth->id(),
            'survey_year'   => $this->repo->surveyYear(),
            'search'        => query('q'),
            'state'         => query('state'),
            'study_state'   => $studyState,
            'department_id' => query_int('dept', 0),
            'limit'         => self::PER_PAGE,
            'offset'        => ($page - 1) * self::PER_PAGE,
        );

        $total = $this->repo->alumniCount($filters);
        $rows = $this->repo->alumniList($filters);

        // Counters ignore the search box: they describe the whole caseload.
        $base = array(
            'school_id'   => $schoolId,
            'advisor_id'  => $this->auth->id(),
            'survey_year' => $filters['survey_year'],
        );
        $graduated = array_merge($base, array('study_state' => 'graduated'));

        $counts = array(
            'total'     => $this->repo->alumniCount($base),
            'studying'  => $this->repo->alumniCount(
                array_merge($base, array('study_state' => 'studying'))
            ),
            'graduated' => $this->repo->alumniCount($graduated),
            // Only graduates owe an employment survey, so the follow-up
            // figures are counted against them alone.
            'updated'   => $this->repo->alumniCount(
                array_merge($graduated, array('state' => 'updated'))
            ),
        );
        $counts['pending'] = max(0, $counts['graduated'] - $counts['updated']);

        $this->render('advisor/index', array(
            'title'       => 'ข้อมูลนักศึกษาในความดูแล',
            'rows'        => $rows,
            'counts'      => $counts,
            'filters'     => $filters,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => self::PER_PAGE,
            'departments' => $this->repo->departments($schoolId),
        ));
    }

    public function summary()
    {
        $this->auth->require_role('advisor');

        $year = $this->repo->surveyYear();
        $schoolId = $this->auth->schoolId();
        $advisorId = $this->auth->id();

        // schoolSummary() covers a whole institution, so build the advisor's
        // narrower slice here.
        $total = $this->repo->alumniCount(array(
            'school_id' => $schoolId, 'advisor_id' => $advisorId, 'survey_year' => $year,
            'study_state' => 'graduated',
        ));

        $rows = $this->repo->all(
            'SELECT st.employment_status AS code, COUNT(*) AS c'
            . ' FROM `{p}alumni` a'
            . ' JOIN `{p}alumni_status` st ON st.alumni_id = a.id AND st.survey_year = ?'
            . ' WHERE a.school_id = ? AND a.advisor_user_id = ? AND st.is_draft = 0'
            . '   AND a.study_state = "graduated"'
            . ' GROUP BY st.employment_status',
            array($year, $schoolId, $advisorId)
        );

        $byStatus = array();
        foreach (array_keys(employment_statuses()) as $code) {
            $byStatus[$code] = 0;
        }
        $updated = 0;
        foreach ($rows as $row) {
            $count = (int) $row['c'];
            $updated += $count;
            if (isset($byStatus[$row['code']])) {
                $byStatus[$row['code']] = $count;
            }
        }

        $summary = array(
            'total'     => $total,
            'updated'   => $updated,
            'by_status' => $byStatus,
            'employed'  => $byStatus['employed_match'] + $byStatus['employed_other'] + $byStatus['freelance'],
            'study'     => $byStatus['study'],
        );

        $this->render('advisor/summary', array(
            'title'   => 'สรุปกลุ่ม',
            'summary' => $summary,
            'year'    => $year,
        ));
    }

    /**
     * Fill the survey in for one alumnus. Reuses the same view and the same
     * field-collection logic as the alumnus' own screen.
     */
    public function fill()
    {
        $this->auth->require_role(array('advisor', 'schooladmin'));

        $alumniId = is_post() ? post_int('id', query_int('id', 0)) : query_int('id', 0);
        if ($alumniId < 1) {
            $alumniId = query_int('id', 0);
        }
        $alumni = $this->repo->alumni($alumniId);

        if ($alumni === null || (int) $alumni['school_id'] !== (int) $this->auth->schoolId()) {
            http_response_code(404);
            flash('error', 'ไม่พบข้อมูลศิษย์เก่ารายนี้ในสถานศึกษาของคุณ');
            redirect('advisor');
        }
        // The roster no longer offers current students here, but a hand-typed
        // id still could: the employment survey asks what someone is doing
        // after finishing, which is not a question they can answer yet.
        if (arr($alumni, 'study_state', 'graduated') === 'studying') {
            flash('error', 'ผู้เรียนรายนี้ยังไม่สำเร็จการศึกษา จึงยังไม่ต้องกรอกแบบสำรวจภาวะการมีงานทำ');
            redirect('advisor');
        }
        // An advisor may only touch their own caseload; a school admin may
        // touch anyone in the institution.
        if ($this->auth->is('advisor')
            && (int) $alumni['advisor_user_id'] !== $this->auth->id()
            && $alumni['advisor_user_id'] !== null) {
            http_response_code(403);
            flash('error', 'ศิษย์เก่ารายนี้อยู่ในความดูแลของครูท่านอื่น');
            redirect('advisor');
        }

        $year = $this->repo->surveyYear();

        if (is_post()) {
            csrf_verify();

            $isDraft = post('action') === 'draft';
            $status = post('employment_status');
            if (!$isDraft && !array_key_exists($status, employment_statuses())) {
                flash('error', 'กรุณาเลือกสถานะปัจจุบันก่อนส่งข้อมูล');
                redirect(url('advisor/fill', array('id' => $alumniId)));
            }

            $this->repo->updateAlumniContact($alumniId, array(
                'phone'   => post('phone'),
                'email'   => post('email'),
                'line_id' => post('line_id'),
                'address' => post('address'),
            ));

            $contactState = post('contact_state', 'ok');
            if (!in_array($contactState, array('ok', 'hard', 'unreachable'), true)) {
                $contactState = 'ok';
            }
            $this->repo->setAlumniContactState($alumniId, $contactState, post('contact_note'));

            $alumniController = new AlumniController(
                $this->auth, $this->repo, $this->view, $this->config, $this->route
            );
            $this->repo->saveStatus(
                $alumniId,
                $alumni['school_id'],
                $year,
                $alumniController->collectStatusInput(),
                $isDraft,
                'staff',
                $this->auth->id()
            );

            $this->repo->audit(
                $isDraft ? 'survey.draft.behalf' : 'survey.submit.behalf',
                $alumni['student_code'],
                'ปีสำรวจ ' . $year,
                $this->actor()
            );

            flash('success', 'บันทึกข้อมูลของ ' . $alumni['first_name'] . ' เรียบร้อยแล้ว');
            redirect('advisor');
        }

        $this->render('alumni/form', array(
            'title'    => 'กรอกข้อมูลแทน',
            'alumni'   => $alumni,
            'status'   => $this->repo->statusFor($alumniId, $year),
            'year'     => $year,
            'onBehalf' => true,
        ));
    }
}
