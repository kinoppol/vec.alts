<?php
/**
 * Sign in, sign out and institution sign-up.
 */
class AuthController extends Controller
{
    public function login()
    {
        if ($this->auth->check()) {
            redirect($this->auth->homeRoute());
        }

        // An empty tab means the visitor has not said which kind of account
        // they hold yet, and the screen asks that before it shows any fields.
        // The two kinds take entirely different credentials, so a screen that
        // opened on one of them had staff typing their email into the box
        // asking for a student code.
        $tab = query('tab', '');
        $old = array();

        if (is_post()) {
            csrf_verify();
            $tab = post('tab', '');

            if ($tab === 'staff') {
                $identifier = post('identifier');
                $old['identifier'] = $identifier;
                $result = $this->auth->loginStaff($identifier, post('password'));
            } elseif ($tab === 'alumni') {
                $studentCode = post('student_code');
                $old['student_code'] = $studentCode;
                $result = $this->auth->loginAlumni($studentCode, post('national_id'));
            } else {
                $result = array('ok' => false, 'error' => 'กรุณาเลือกประเภทผู้ใช้งานก่อนเข้าสู่ระบบ');
            }

            if ($result['ok']) {
                $this->repo->audit('login', $this->auth->role(), null, $this->auth->user());
                redirect($this->auth->homeRoute());
            }
            // A failed attempt keeps the visitor on the form they were using,
            // rather than dropping them back at the chooser.
            flash('error', $result['error']);
        }

        if ($tab !== 'staff' && $tab !== 'alumni') {
            $tab = '';
        }

        $this->renderBlank('auth/login', array(
            'title' => $tab === '' ? 'เลือกประเภทผู้ใช้งาน' : 'เข้าสู่ระบบ',
            'tab'   => $tab,
            'old'   => $old,
        ));
    }

    public function logout()
    {
        if (is_post()) {
            csrf_verify();
        }
        $actor = $this->auth->user();
        if ($actor) {
            $this->repo->audit('logout', $actor['role'], null, $actor);
        }
        $this->auth->logout();
        redirect('home');
    }

    /**
     * Creates a pending school plus a pending schooladmin account. The central
     * administrator activates both from the requests screen.
     */
    public function register()
    {
        if ($this->auth->check()) {
            redirect($this->auth->homeRoute());
        }

        $old = array();
        $errors = array();

        if (is_post()) {
            csrf_verify();

            $old = array(
                'school_name'   => post('school_name'),
                'province'      => post('province'),
                'affiliation'   => post('affiliation'),
                'contact_name'  => post('contact_name'),
                'contact_phone' => post('contact_phone'),
                'contact_email' => post('contact_email'),
            );
            $password = post('password');
            $confirm = post('password_confirm');

            if ($old['school_name'] === '') {
                $errors['school_name'] = 'กรุณากรอกชื่อสถานศึกษา';
            }
            if ($old['contact_name'] === '') {
                $errors['contact_name'] = 'กรุณากรอกชื่อผู้ประสานงาน';
            }
            if (!filter_var($old['contact_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['contact_email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
            } elseif ($this->repo->userByEmail($old['contact_email']) !== null) {
                $errors['contact_email'] = 'อีเมลนี้ถูกใช้งานแล้วในระบบ';
            }
            if (mb_strlen($password) < 8) {
                $errors['password'] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
            }
            if ($password !== $confirm) {
                $errors['password_confirm'] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
            }

            if (!$errors) {
                $db = $this->repo->db();
                $db->beginTransaction();
                try {
                    $schoolId = $this->repo->createSchool(array(
                        'name'          => $old['school_name'],
                        'province'      => $old['province'],
                        'affiliation'   => $old['affiliation'],
                        'contact_name'  => $old['contact_name'],
                        'contact_phone' => $old['contact_phone'],
                        'contact_email' => $old['contact_email'],
                        'status'        => 'pending',
                    ));
                    $this->repo->createUser(array(
                        'school_id' => $schoolId,
                        'role'      => 'schooladmin',
                        'email'     => $old['contact_email'],
                        'password'  => $password,
                        'full_name' => $old['contact_name'],
                        'phone'     => $old['contact_phone'],
                        'status'    => 'pending',
                    ));
                    $db->commit();
                } catch (PDOException $e) {
                    $db->rollBack();
                    app_log('register failed: ' . $e->getMessage());
                    flash('error', 'บันทึกคำขอไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
                    $this->renderBlank('auth/register', array(
                        'title' => 'สมัครใช้งาน', 'old' => $old, 'errors' => $errors,
                    ));
                    return;
                }

                $this->repo->audit('school.register', $old['school_name'], $old['contact_email']);
                flash('success', 'ส่งคำขอเรียบร้อยแล้ว ผู้ดูแลระบบกลางจะตรวจสอบและเปิดใช้งานให้ภายใน 1-2 วันทำการ');
                redirect(url('login', array('tab' => 'staff')));
            }

            flash('error', 'กรุณาตรวจสอบข้อมูลที่กรอกอีกครั้ง');
        }

        $this->renderBlank('auth/register', array(
            'title'  => 'สมัครใช้งาน',
            'old'    => $old,
            'errors' => $errors,
        ));
    }
}
