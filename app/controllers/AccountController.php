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
    public function password()
    {
        $this->auth->require_role(array('advisor', 'exec', 'schooladmin', 'centraladmin'));

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
}
