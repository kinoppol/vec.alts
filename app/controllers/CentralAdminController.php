<?php
/**
 * Central administration across every institution.
 */
class CentralAdminController extends Controller
{
    public function index()
    {
        $this->auth->require_role('centraladmin');

        $this->render('centraladmin/index', array(
            'title'   => 'สถานศึกษาทั้งหมด',
            'summary' => $this->repo->centralSummary(),
            'schools' => $this->repo->schoolsWithCounts(),
        ));
    }

    public function requests()
    {
        $this->auth->require_role('centraladmin');

        $this->render('centraladmin/requests', array(
            'title'    => 'คำขอสมัคร',
            'requests' => $this->repo->schools('pending'),
        ));
    }

    /**
     * Approving a school also activates the pending schooladmin account that
     * was created with it, otherwise nobody could sign in to the new site.
     */
    public function schoolStatus()
    {
        $this->auth->require_role('centraladmin');
        csrf_verify();

        $id = post_int('id', 0);
        $status = post('status');
        if (!in_array($status, array('active', 'suspended', 'pending'), true)) {
            flash('error', 'สถานะไม่ถูกต้อง');
            redirect('centraladmin');
        }

        $school = $this->repo->school($id);
        if ($school === null) {
            flash('error', 'ไม่พบสถานศึกษา');
            redirect('centraladmin');
        }

        $db = $this->repo->db();
        $db->beginTransaction();
        try {
            $this->repo->setSchoolStatus($id, $status);
            if ($status === 'active') {
                $this->repo->run(
                    'UPDATE `{p}users` SET status = ?, updated_at = ?'
                    . ' WHERE school_id = ? AND status = ?',
                    array('active', date('Y-m-d H:i:s'), $id, 'pending')
                );
            }
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            app_log('school status change failed: ' . $e->getMessage());
            flash('error', 'ปรับสถานะไม่สำเร็จ');
            redirect('centraladmin');
        }

        $this->repo->audit('school.status', $school['name'], $status, $this->actor());
        flash('success', $status === 'active'
            ? 'เปิดใช้งาน ' . $school['name'] . ' เรียบร้อยแล้ว'
            : 'ปรับสถานะ ' . $school['name'] . ' เรียบร้อยแล้ว');

        redirect(query('from') === 'requests' ? 'centraladmin/requests' : 'centraladmin');
    }

    public function users()
    {
        $this->auth->require_role('centraladmin');

        $search = query('q');
        $schoolId = query_int('school', 0);

        $where = array('1 = 1');
        $params = array();
        if ($schoolId > 0) {
            $where[] = 'u.school_id = ?';
            $params[] = $schoolId;
        }
        if ($search !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $users = $this->repo->all(
            'SELECT u.*, s.name AS school_name FROM `{p}users` u'
            . ' LEFT JOIN `{p}schools` s ON s.id = u.school_id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY s.name ASC, u.full_name ASC LIMIT 300',
            $params
        );

        $this->render('centraladmin/users', array(
            'title'   => 'ผู้ใช้งานระบบ',
            'users'   => $users,
            'schools' => $this->repo->schools(),
            'filters' => array('search' => $search, 'school_id' => $schoolId),
        ));
    }

    public function settings()
    {
        $this->auth->require_role('centraladmin');

        if (is_post()) {
            csrf_verify();

            $title = post('site_title');
            if ($title !== '') {
                $this->repo->setSetting('site_title', $title);
            }

            $year = post_int('survey_year', 0);
            if ($year >= 2500 && $year <= 2700) {
                $this->repo->setSetting('survey_year', $year);
            } else {
                flash('warn', 'ปีสำรวจต้องอยู่ระหว่าง 2500 ถึง 2700 — ไม่ได้บันทึกค่านี้');
            }

            $this->repo->setSetting('allow_self_update', post('allow_self_update') === '1' ? '1' : '0');
            $this->repo->setSetting('allow_school_register', post('allow_school_register') === '1' ? '1' : '0');

            $this->repo->audit('settings.update', 'system', null, $this->actor());
            flash('success', 'บันทึกการตั้งค่าเรียบร้อยแล้ว');
            redirect('centraladmin/settings');
        }

        $this->render('centraladmin/settings', array(
            'title'    => 'ตั้งค่าระบบ',
            'settings' => array(
                'site_title'        => $this->repo->setting('site_title', 'ระบบติดตามศิษย์เก่า'),
                'survey_year'       => $this->repo->surveyYear(),
                'allow_self_update' => $this->repo->setting('allow_self_update', '1'),
                'allow_school_register' => $this->repo->setting('allow_school_register', '1'),
            ),
            'env'      => $this->environment(),
        ));
    }
}
