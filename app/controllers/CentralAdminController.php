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

    /**
     * Adds an institution directly, without waiting for it to sign itself up.
     *
     * This is the only way in once public sign-ups are closed, so it also
     * offers to create the institution's administrator in the same step —
     * a school with nobody able to manage it is of no use.
     */
    public function schoolCreate()
    {
        $this->auth->require_role('centraladmin');

        $old = array();
        $errors = array();

        if (is_post()) {
            csrf_verify();

            $old = array(
                'name'          => post('name'),
                'code'          => post('code'),
                'province'      => post('province'),
                'affiliation'   => post('affiliation'),
                'rms_base_url'  => rtrim(post('rms_base_url'), '/'),
                'contact_name'  => post('contact_name'),
                'contact_phone' => post('contact_phone'),
                'contact_email' => post('contact_email'),
                'status'        => post('status', 'active'),
                'note'          => post('note'),
                'with_admin'    => post('with_admin') === '1',
                'admin_name'    => post('admin_name'),
                'admin_email'   => post('admin_email'),
            );
            $adminPassword = post('admin_password');

            if ($old['name'] === '') {
                $errors['name'] = 'กรุณากรอกชื่อสถานศึกษา';
            } elseif ($this->repo->schoolByName($old['name']) !== null) {
                $errors['name'] = 'มีสถานศึกษาชื่อนี้อยู่ในระบบแล้ว';
            }

            if (!in_array($old['status'], array('active', 'pending', 'suspended'), true)) {
                $old['status'] = 'active';
            }

            if ($old['contact_email'] !== '' && !filter_var($old['contact_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['contact_email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
            }

            if ($old['rms_base_url'] !== '') {
                $urlError = Http::validateUrl($old['rms_base_url']);
                if ($urlError !== '') {
                    $errors['rms_base_url'] = $urlError;
                }
            }

            if ($old['with_admin']) {
                if ($old['admin_name'] === '') {
                    $errors['admin_name'] = 'กรุณากรอกชื่อผู้ดูแลสถานศึกษา';
                }
                if (!filter_var($old['admin_email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['admin_email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
                } elseif ($this->repo->userByEmail($old['admin_email']) !== null) {
                    $errors['admin_email'] = 'อีเมลนี้ถูกใช้งานแล้วในระบบ';
                }
                if (mb_strlen($adminPassword) < 8) {
                    $errors['admin_password'] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
                }
            }

            if (!$errors) {
                $db = $this->repo->db();
                $db->beginTransaction();
                try {
                    $schoolId = $this->repo->createSchool(array(
                        'name'          => $old['name'],
                        'code'          => $old['code'],
                        'province'      => $old['province'],
                        'affiliation'   => $old['affiliation'],
                        'rms_base_url'  => $old['rms_base_url'],
                        'contact_name'  => $old['contact_name'],
                        'contact_phone' => $old['contact_phone'],
                        'contact_email' => $old['contact_email'],
                        'status'        => $old['status'],
                        'note'          => $old['note'] !== '' ? $old['note'] : null,
                    ));

                    if ($old['with_admin']) {
                        $this->repo->createUser(array(
                            'school_id' => $schoolId,
                            'role'      => 'schooladmin',
                            'email'     => $old['admin_email'],
                            'password'  => $adminPassword,
                            'full_name' => $old['admin_name'],
                            'phone'     => $old['contact_phone'],
                            // Added by the central admin, so it is usable at once.
                            'status'    => 'active',
                        ));
                    }
                    $db->commit();
                } catch (PDOException $e) {
                    $db->rollBack();
                    app_log('school create failed: ' . $e->getMessage());
                    flash('error', 'บันทึกสถานศึกษาไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
                    $this->render('centraladmin/school-create', array(
                        'title' => 'เพิ่มสถานศึกษา', 'old' => $old, 'errors' => $errors,
                        'defaultRmsUrl' => $this->repo->setting('rms_base_url', ''),
                    ));
                    return;
                }

                $this->repo->audit('school.create', $old['name'], $old['status'], $this->actor());
                flash('success', 'เพิ่ม ' . $old['name'] . ' เรียบร้อยแล้ว'
                    . ($old['with_admin'] ? ' พร้อมบัญชีผู้ดูแลสถานศึกษา' : ''));
                redirect('centraladmin');
            }

            flash('error', 'กรุณาตรวจสอบข้อมูลที่กรอกอีกครั้ง');
        }

        $this->render('centraladmin/school-create', array(
            'title'         => 'เพิ่มสถานศึกษา',
            'old'           => $old,
            'errors'        => $errors,
            'defaultRmsUrl' => $this->repo->setting('rms_base_url', ''),
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

            // Only the origin is stored; the endpoint path lives in
            // RmsImporter::API_PATH so the integration cannot be repointed at
            // an arbitrary script from the settings screen.
            $baseUrl = rtrim(post('rms_base_url'), '/');
            if ($baseUrl === '') {
                $this->repo->setSetting('rms_base_url', '');
            } else {
                $urlError = Http::validateUrl($baseUrl);
                if ($urlError !== '') {
                    flash('warn', 'ที่อยู่ระบบ RMS ไม่ถูกต้อง (' . $urlError . ') — ไม่ได้บันทึกค่านี้');
                } else {
                    $this->repo->setSetting('rms_base_url', $baseUrl);
                }
            }

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
                'rms_base_url'      => $this->repo->setting('rms_base_url', ''),
            ),
            'env'      => $this->environment(),
            'rmsApiPath' => RmsImporter::API_PATH,
        ));
    }

    /**
     * Transfers staff accounts in from the configured RMS installation.
     */
    public function importUsers()
    {
        $this->auth->require_role('centraladmin');

        // Which institution is being transferred into decides where the data
        // comes from: each one carries its own RMS address, falling back to
        // the system-wide default when it has none.
        $selectedSchool = is_post() ? post_int('school_id', 0) : query_int('school_id', 0);
        $baseUrl = $this->repo->rmsBaseUrlFor($selectedSchool > 0 ? $selectedSchool : null);

        $importer = new RmsImporter($this->repo, $baseUrl);
        $summary = null;
        $preview = null;

        if (is_post()) {
            csrf_verify();

            if (trim($baseUrl) === '') {
                flash('error', $selectedSchool > 0
                    ? 'สถานศึกษาที่เลือกยังไม่ได้กำหนดที่อยู่ระบบ RMS และยังไม่มีค่าเริ่มต้นของระบบ'
                    : 'ยังไม่ได้กำหนดที่อยู่ระบบ RMS กรุณาตั้งค่าที่เมนูตั้งค่าระบบก่อน');
                redirect(url('centraladmin/import-users', array('school_id' => $selectedSchool)));
            }

            $feed = $importer->fetch();
            if (!$feed['ok']) {
                flash('error', 'ดึงข้อมูลไม่สำเร็จ: ' . $feed['error']);
                redirect('centraladmin/import-users');
            }

            $action = post('action');

            if ($action === 'preview') {
                $preview = array(
                    'total'        => $feed['total'],
                    'eligible'     => count($feed['people']),
                    'skipped_exit' => $feed['skipped_exit'],
                    'sample'       => array_slice($feed['people'], 0, 8),
                );
                flash('info', 'ตรวจสอบข้อมูลเรียบร้อย ยังไม่ได้บันทึกลงฐานข้อมูล');

            } elseif ($action === 'import') {
                // PHP on the production host may cap execution at 30 seconds,
                // and there are hundreds of pictures to fetch. The transfer of
                // the accounts themselves always completes; pictures run until
                // the budget is spent and the rest are picked up next time.
                @set_time_limit(0);
                $deadline = time() + 20;

                $summary = $importer->import($feed['people'], array(
                    'school_id'        => post_int('school_id', 0) > 0 ? post_int('school_id', 0) : null,
                    'role'             => post('role', 'advisor'),
                    'update_passwords' => post('update_passwords') === '1',
                    'avatars'          => post('avatars') === '1',
                    'deadline'         => $deadline,
                ));

                $this->repo->setSetting('rms_last_import_at', date('Y-m-d H:i:s'));
                $this->repo->audit(
                    'users.import.rms',
                    $importer->feedUrl(),
                    'created=' . $summary['created'] . ' updated=' . $summary['updated']
                        . ' failed=' . $summary['failed'],
                    $this->actor()
                );

                flash('success', 'โอนข้อมูลเรียบร้อย: เพิ่มใหม่ ' . $summary['created']
                    . ' คน · ปรับปรุง ' . $summary['updated'] . ' คน');

            } elseif ($action === 'avatars') {
                @set_time_limit(0);
                $caught = $importer->catchUpAvatars($feed['people'], time() + 20);

                if (isset($caught['blocked'])) {
                    // Nothing was even attempted; the storage is the problem.
                    flash('error', 'ดาวน์โหลดรูปไม่ได้: ' . $caught['blocked']);
                } else {
                    flash(
                        $caught['failed'] > 0 ? 'warn' : 'success',
                        'ดาวน์โหลดรูปเพิ่ม ' . $caught['saved'] . ' รูป · ไม่สำเร็จ '
                        . $caught['failed'] . ' รูป'
                        . ($caught['pending'] > 0
                            ? ' · ยังเหลืออีก กดปุ่มเดิมอีกครั้งเพื่อทำต่อ' : '')
                    );
                    foreach ($caught['avatar_reasons'] as $reason => $count) {
                        flash('error', 'สาเหตุ (' . $count . ' รูป): ' . $reason);
                    }
                }
                redirect(url('centraladmin/import-users', array('school_id' => $selectedSchool)));
            }
        }

        $this->render('centraladmin/import-users', array(
            'title'      => 'โอนข้อมูลผู้ใช้',
            'baseUrl'    => $baseUrl,
            'feedUrl'    => trim($baseUrl) === '' ? '' : $importer->feedUrl(),
            'apiPath'    => RmsImporter::API_PATH,
            'schools'    => $this->repo->schools('active'),
            'selectedSchool' => $selectedSchool,
            'defaultRmsUrl'  => $this->repo->setting('rms_base_url', ''),
            // Shown up front: without writable storage or a way to make an
            // outbound request, every picture will fail for the same reason.
            'storage'        => RmsImporter::storageStatus(),
            'canFetch'       => function_exists('curl_init') || ini_get('allow_url_fopen'),
            'summary'    => $summary,
            'preview'    => $preview,
            'lastImport' => $this->repo->setting('rms_last_import_at', ''),
            'pendingAvatars' => count($this->repo->usersMissingAvatar(RmsImporter::SOURCE)),
        ));
    }
}
