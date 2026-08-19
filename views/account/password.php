<?php
/**
 * Change my own password. Linked from the sidebar of every staff role.
 *
 * @var array $user the signed-in staff row
 */
?>
<h1 class="page-title">เปลี่ยนรหัสผ่าน</h1>
<p class="page-sub">
  บัญชี <?php echo e($user['email']); ?> · <?php echo e(role_label($user['role'])); ?>
</p>

<div class="card card-lg" style="max-width:520px">
  <form method="post" action="<?php echo e(url('account/password')); ?>" autocomplete="off">
    <?php echo csrf_field(); ?>

    <div class="field">
      <label class="label" for="current_password">รหัสผ่านปัจจุบัน</label>
      <div class="input-reveal">
        <input class="input" type="password" id="current_password" name="current_password"
               required autocomplete="current-password">
        <button type="button" class="reveal-btn" data-reveal-password="current_password"
                aria-controls="current_password" aria-pressed="false"
                aria-label="แสดงรหัสผ่าน" hidden>แสดง</button>
      </div>
    </div>

    <div class="field">
      <label class="label" for="new_password">รหัสผ่านใหม่</label>
      <div class="input-reveal">
        <input class="input" type="password" id="new_password" name="new_password"
               required minlength="8" autocomplete="new-password">
        <button type="button" class="reveal-btn" data-reveal-password="new_password"
                aria-controls="new_password" aria-pressed="false"
                aria-label="แสดงรหัสผ่าน" hidden>แสดง</button>
      </div>
      <div class="hint">อย่างน้อย 8 ตัวอักษร และต้องไม่ซ้ำกับรหัสผ่านเดิม</div>
    </div>

    <div class="field">
      <label class="label" for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
      <div class="input-reveal">
        <input class="input" type="password" id="confirm_password" name="confirm_password"
               required minlength="8" autocomplete="new-password">
        <button type="button" class="reveal-btn" data-reveal-password="confirm_password"
                aria-controls="confirm_password" aria-pressed="false"
                aria-label="แสดงรหัสผ่าน" hidden>แสดง</button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">บันทึกรหัสผ่านใหม่</button>
  </form>
</div>

<p class="page-sub" style="max-width:520px;margin-top:18px">
  ลืมรหัสผ่านจนเข้าระบบไม่ได้? ผู้ดูแลระบบกลางตั้งรหัสผ่านใหม่ให้ได้จาก
  <code>install.php</code> ที่เครื่องให้บริการ
</p>
