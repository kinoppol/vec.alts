<?php
/**
 * The signed-in staff member's own account.
 *
 * Every role that signs in with a password gets here, not just the
 * administrators: the mechanism is identical for all of them, and an advisor
 * who cannot change their own password has to ask someone else to do it.
 *
 * Alumni are excluded on purpose. They authenticate with their national ID,
 * which is stored only as a hash and is not a credential the system may let
 * anyone rewrite.
 */
class AccountController extends Controller
{
    /** Roles that sign in with a password and therefore have an account here. */
    private static function selfServiceRoles()
    {
        return array('advisor', 'exec', 'schooladmin', 'centraladmin');
    }

    public function profile()
    {
        $this->auth->require_role(self::selfServiceRoles());

        $user = $this->repo->user($this->auth->id());
        if ($user === null) {
            flash('error', 'ไม่พบบัญชีผู้ใช้ของคุณ');
            $this->auth->logout();
            redirect('login');
        }

        if (is_post()) {
            csrf_verify();

            $fullName = post('full_name');
            $email = post('email');
            $phone = post('phone');

            if ($fullName === '') {
                flash('error', 'กรุณากรอกชื่อ-นามสกุล');
                redirect('account/profile');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'รูปแบบอีเมลไม่ถูกต้อง');
                redirect('account/profile');
            }

            // The email is also a sign-in identifier, so it has to stay unique
            // across the whole system, not just within this institution.
            $clash = $this->repo->userByEmail($email);
            if ($clash !== null && (int) $clash['id'] !== (int) $user['id']) {
                flash('error', 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว');
                redirect('account/profile');
            }

            $this->repo->updateUserProfile($user['id'], array(
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => $phone,
            ));

            // The session holds a copy of the row from sign-in time; refresh
            // the name so the audit log stops recording the old one.
            $this->auth->updateIdentity(array('name' => $fullName));

            $detail = ($email !== $user['email'])
                ? 'เปลี่ยนอีเมลเข้าสู่ระบบจาก ' . $user['email'] . ' เป็น ' . $email
                : null;
            $this->repo->audit('account.profile', $email, $detail, $this->actor());

            flash('success', $email !== $user['email']
                ? 'บันทึกโปรไฟล์เรียบร้อยแล้ว ครั้งต่อไปให้เข้าสู่ระบบด้วยอีเมลใหม่'
                : 'บันทึกโปรไฟล์เรียบร้อยแล้ว');
            redirect('account/profile');
        }

        $this->render('account/profile', array(
            'title'  => 'โปรไฟล์ของฉัน',
            'user'   => $user,
            'school' => $this->currentSchool(),
        ));
    }

    public function password()
    {
        $this->auth->require_role(self::selfServiceRoles());

        $user = $this->repo->user($this->auth->id());
        if ($user === null) {
            flash('error', 'ไม่พบบัญชีผู้ใช้ของคุณ');
            $this->auth->logout();
            redirect('login');
        }

        if (is_post()) {
            csrf_verify();

            $current = post('current_password');
            $new = post('new_password');
            $confirm = post('confirm_password');

            // The current password is required so that a session left open on
            // a shared staff-room machine cannot be turned into a permanent
            // takeover of the account.
            if (!password_verify($current, $user['password_hash'])) {
                $this->repo->audit(
                    'account.password_failed',
                    $user['email'],
                    'รหัสผ่านปัจจุบันไม่ถูกต้อง',
                    $this->actor()
                );
                flash('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
                redirect('account/password');
            }
            if (mb_strlen($new) < 8) {
                flash('error', 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร');
                redirect('account/password');
            }
            if ($new !== $confirm) {
                flash('error', 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
                redirect('account/password');
            }
            if ($new === $current) {
                flash('error', 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม');
                redirect('account/password');
            }

            $this->repo->setUserPassword($user['id'], $new);
            $this->repo->audit('account.password', $user['email'], null, $this->actor());

            flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว ครั้งต่อไปให้เข้าสู่ระบบด้วยรหัสผ่านใหม่');
            redirect('account/password');
        }

        $this->render('account/password', array(
            'title' => 'เปลี่ยนรหัสผ่าน',
            'user'  => $user,
        ));
    }

    /**
     * A student or graduate choosing their own password.
     *
     * Reached two ways: forced, straight after signing in with a teacher's
     * one-time code, when nothing else is reachable until this is done; or
     * from the menu, when the current password is asked for first so an
     * unattended session cannot be turned into a permanent takeover.
     *
     * Not guarded by require_role(), which would bounce the forced session
     * straight back here.
     */
    public function alumniPassword()
    {
        $identity = $this->auth->user();
        if ($identity === null || arr($identity, 'kind') !== 'alumni') {
            if ($identity === null) {
                flash('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
                redirect('login');
            }
            redirect($this->auth->homeRoute());
        }

        $person = $this->repo->alumni($this->auth->id());
        if ($person === null) {
            $this->auth->logout();
            redirect('login');
        }
        $forced = $this->auth->mustSetPassword();

        if (is_post()) {
            csrf_verify();

            $new = post('new_password');
            $confirm = post('confirm_password');

            if (!$forced) {
                $hash = (string) arr($person, 'password_hash', '');
                // Someone still on the transitional ID-only sign-in has no
                // password to confirm; asking for the ID instead would make
                // the leaked ID enough to take the account over.
                if ($hash === '' || !password_verify(post('current_password'), $hash)) {
                    $this->repo->audit('alumni.password_failed', $person['student_code'],
                        'รหัสผ่านปัจจุบันไม่ถูกต้อง', $this->actor());
                    flash('error', $hash === ''
                        ? 'บัญชีนี้ยังไม่ได้ตั้งรหัสผ่าน กรุณาขอรหัสเข้าใช้ครั้งแรกจากครูที่ปรึกษา'
                        : 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
                    redirect('account/set-password');
                }
            }

            $problem = Auth::alumniPasswordProblem($new, $person);
            if ($problem === '' && $new !== $confirm) {
                $problem = 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
            }
            if ($problem === '' && !$forced && $new === post('current_password')) {
                $problem = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม';
            }
            if ($problem !== '') {
                flash('error', $problem);
                redirect('account/set-password');
            }

            $this->repo->setAlumniPassword($person['id'], $new);
            $this->auth->updateIdentity(array('must_set_password' => false));
            $this->repo->audit(
                $forced ? 'alumni.password_set' : 'alumni.password_change',
                $person['student_code'],
                $forced ? 'ตั้งรหัสผ่านด้วยรหัสเข้าใช้ครั้งแรก' : null,
                $this->actor()
            );

            flash('success', $forced
                ? 'ตั้งรหัสผ่านเรียบร้อยแล้ว ครั้งต่อไปให้เข้าสู่ระบบด้วยรหัสนักศึกษาและรหัสผ่านนี้'
                : 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
            redirect($this->auth->homeRoute());
        }

        $this->render('account/alumni-password', array(
            'title'  => $forced ? 'ตั้งรหัสผ่าน' : 'เปลี่ยนรหัสผ่าน',
            'person' => $person,
            'forced' => $forced,
        ));
    }
}
