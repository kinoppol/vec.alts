<?php
/**
 * One-time codes that let a student or graduate set their first password,
 * or a new one after forgetting it.
 *
 * Teachers issue them because teachers are who these people can actually
 * reach in person; the code is what a leaked national ID alone does not have.
 */
class AccessCodeController extends Controller
{
    /** Most codes one request will issue, so a slip page stays printable. */
    const MAX_BATCH = 500;

    /**
     * Issues codes for one person or for everyone matching the list filters,
     * then shows the printable slips.
     */
    public function issue()
    {
        $this->auth->require_role(array('advisor', 'schooladmin'));
        csrf_verify();

        $schoolId = $this->auth->schoolId();
        $isAdvisor = $this->auth->is('advisor');
        $back = $isAdvisor ? 'advisor' : 'schooladmin/alumni';

        $people = array();
        if (post('scope') === 'filtered') {
            $filters = array(
                'school_id'     => $schoolId,
                'survey_year'   => $this->repo->surveyYear(),
                'search'        => post('q'),
                'state'         => post('state'),
                'study_state'   => post('study'),
                'department_id' => post_int('dept', 0),
                'limit'         => self::MAX_BATCH + 1,
                'offset'        => 0,
            );
            // An advisor's batch is their own caseload, whatever the form says.
            if ($isAdvisor) {
                $filters['advisor_id'] = $this->auth->id();
            }
            $onlyUnset = post('only_unset') === '1';
            foreach ($this->repo->alumniList($filters) as $row) {
                if ($onlyUnset && (string) arr($row, 'password_hash', '') !== '') {
                    continue;
                }
                $people[] = $row;
            }
            if (count($people) > self::MAX_BATCH) {
                flash('error', 'ออกรหัสได้ครั้งละไม่เกิน ' . self::MAX_BATCH
                    . ' คน กรุณากรองรายชื่อให้แคบลง เช่น เลือกสาขา');
                redirect($back);
            }
        } else {
            // Reloaded and checked rather than trusted: an id from a request
            // never proves the person belongs to this institution.
            $person = $this->repo->alumni(post_int('id', 0));
            if ($person === null || (int) $person['school_id'] !== (int) $schoolId) {
                flash('error', 'ไม่พบข้อมูลนักศึกษารายนี้ในสถานศึกษาของคุณ');
                redirect($back);
            }
            if ($isAdvisor && $person['advisor_user_id'] !== null
                && (int) $person['advisor_user_id'] !== $this->auth->id()) {
                flash('error', 'นักศึกษารายนี้อยู่ในความดูแลของครูท่านอื่น');
                redirect($back);
            }
            $people[] = $person;
        }

        if (!$people) {
            flash('warn', 'ไม่มีรายชื่อที่ต้องออกรหัสตามเงื่อนไขที่เลือก');
            redirect($back);
        }

        $slips = array();
        foreach ($people as $person) {
            $issued = $this->repo->issueAccessCode($person['id']);
            $slips[] = array(
                'name'       => trim($person['title'] . $person['first_name'] . ' ' . $person['last_name']),
                'student_code' => $person['student_code'],
                'department' => arr($person, 'department_name', ''),
                'code'       => $issued['code'],
                'expires_at' => $issued['expires_at'],
                'has_password' => (string) arr($person, 'password_hash', '') !== '',
            );
        }

        $this->repo->audit(
            'alumni.access_code',
            count($people) === 1 ? $people[0]['student_code'] : count($people) . ' คน',
            'ออกรหัสเข้าใช้ครั้งแรก',
            $this->actor()
        );

        // Held for exactly one page view: the codes exist nowhere else in
        // plain text, and must not stay readable in the session afterwards.
        $_SESSION['access_code_slips'] = array(
            'slips'  => $slips,
            'back'   => $back,
            'issuer' => arr($this->actor(), 'name', ''),
            'school' => arr($this->actor(), 'school_name', ''),
        );
        redirect('access-codes/slips');
    }

    /**
     * Printable slips for the codes just issued. Shown once.
     */
    public function slips()
    {
        $this->auth->require_role(array('advisor', 'schooladmin'));

        $batch = isset($_SESSION['access_code_slips']) ? $_SESSION['access_code_slips'] : null;
        unset($_SESSION['access_code_slips']);

        if (!is_array($batch) || empty($batch['slips'])) {
            flash('warn', 'ใบแจ้งรหัสแสดงได้ครั้งเดียว หากทำหน้านี้หาย ให้ออกรหัสใหม่อีกครั้ง');
            redirect($this->auth->is('advisor') ? 'advisor' : 'schooladmin/alumni');
        }

        header('Cache-Control: no-store');
        $this->renderBlank('access-codes/slips', array(
            'title'  => 'ใบแจ้งรหัสเข้าใช้ครั้งแรก',
            'batch'  => $batch,
            'appName' => $this->repo->setting('site_title', 'ระบบติดตามข้อมูลนักศึกษา'),
        ));
    }
}
