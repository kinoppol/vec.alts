<?php
/**
 * Advisor worklist and filling the survey in on an alumnus' behalf.
 */
class AdvisorController extends Controller
{
    /** Rows per page when the advisor has not chosen otherwise. */
    const PER_PAGE = 10;

    /**
     * Page sizes an advisor may pick.
     *
     * A method rather than a constant: array constants only arrived in
     * PHP 5.6 and the production box runs 5.4.
     *
     * @return array
     */
    public static function perPageOptions()
    {
        return array(10, 25, 50, 100);
    }

    /**
     * The page size for this request.
     *
     * A choice in the query string wins and is remembered, so paging through
     * the list keeps it and coming back later does too. Anything not on the
     * list is ignored rather than trusted.
     *
     * @return int
     */
    private function resolvePerPage()
    {
        $options = self::perPageOptions();

        $requested = query_int('per', 0);
        if (in_array($requested, $options, true)) {
            $_SESSION['advisor_per_page'] = $requested;
            return $requested;
        }

        if (isset($_SESSION['advisor_per_page'])
            && in_array((int) $_SESSION['advisor_per_page'], $options, true)) {
            return (int) $_SESSION['advisor_per_page'];
        }

        return self::PER_PAGE;
    }

    public function index()
    {
        $this->auth->require_role('advisor');

        $schoolId = $this->auth->schoolId();
        $page = max(1, query_int('page', 1));
        $perPage = $this->resolvePerPage();

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
            'limit'         => $perPage,
            'offset'        => ($page - 1) * $perPage,
        );

        $total = $this->repo->alumniCount($filters);

        // A smaller page size, or a narrower filter, can leave the advisor
        // past the end of the results.
        $pages = (int) ceil($total / $perPage);
        if ($page > $pages && $pages > 0) {
            $page = $pages;
            $filters['offset'] = ($page - 1) * $perPage;
        }

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
            'page'           => $page,
            'pages'          => $pages,
            'perPage'        => $perPage,
            'perPageOptions' => self::perPageOptions(),
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
     * Updates one current student in the advisor's caseload.
     *
     * The employment survey only opens once someone has finished, but their
     * contact details and plans go stale long before then, so an advisor can
     * keep those current at any time.
     *
     * Anything RMS supplies is left alone. It is the source of truth for those
     * fields and overwrites them on the next transfer, so offering them for
     * editing here would promise a change that quietly disappears.
     */
    public function student()
    {
        $this->auth->require_role(array('advisor', 'schooladmin'));

        $studentId = is_post() ? post_int('id', 0) : query_int('id', 0);
        $student = $this->repo->alumni($studentId);

        if ($student === null || (int) $student['school_id'] !== (int) $this->auth->schoolId()) {
            http_response_code(404);
            flash('error', 'ไม่พบข้อมูลนักศึกษารายนี้ในสถานศึกษาของคุณ');
            redirect('advisor');
        }
        if (arr($student, 'study_state', 'graduated') !== 'studying') {
            // Graduates belong on the survey screen, which records a great
            // deal more than this one does.
            redirect(url('advisor/fill', array('id' => $studentId)));
        }
        if ($this->auth->is('advisor')
            && (int) $student['advisor_user_id'] !== $this->auth->id()
            && $student['advisor_user_id'] !== null) {
            http_response_code(403);
            flash('error', 'นักศึกษารายนี้อยู่ในความดูแลของครูท่านอื่น');
            redirect('advisor');
        }

        // Contact details are RMS's to own only when the record came from
        // there; one entered by hand or from a CSV has nothing to overwrite it.
        $fromRms = arr($student, 'external_source', '') === RmsImporter::SOURCE;

        if (is_post()) {
            csrf_verify();

            $this->repo->updateAlumniContact($studentId, array(
                // Kept as they are for an RMS record rather than read from the
                // form, so a crafted request cannot get round the lock either.
                'phone'   => $fromRms ? $student['phone'] : post('phone'),
                'email'   => $fromRms ? $student['email'] : post('email'),
                'line_id' => post('line_id'),
                'address' => post('address'),
            ));

            $this->repo->updateAlumniPlan($studentId, post('plan_after'), post('plan_note'));

            $contactState = post('contact_state', 'ok');
            if (!in_array($contactState, array('ok', 'hard', 'unreachable'), true)) {
                $contactState = 'ok';
            }
            $this->repo->setAlumniContactState($studentId, $contactState, post('contact_note'));

            $this->repo->audit(
                'student.update.behalf',
                $student['student_code'],
                'ครูที่ปรึกษาปรับปรุงข้อมูลศิษย์ปัจจุบัน',
                $this->actor()
            );

            flash('success', 'บันทึกข้อมูลของ ' . $student['first_name'] . ' เรียบร้อยแล้ว');
            redirect('advisor');
        }

        $this->render('advisor/student', array(
            'title'   => 'ปรับปรุงข้อมูลนักศึกษา',
            'student' => $student,
            'fromRms' => $fromRms,
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
