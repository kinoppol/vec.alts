<?php
/**
 * A student or graduate choosing their password: forced straight after a
 * one-time code, or voluntary from the menu.
 *
 * @var array $person the signed-in alumni row
 * @var bool $forced  true right after signing in with a one-time code
 */
?>
<h1 class="page-title"><?php echo $forced ? 'ตั้งรหัสผ่านของคุณ' : 'เปลี่ยนรหัสผ่าน'; ?></h1>
<p class="page-sub">
  รหัสนักศึกษา <?php echo e($person['student_code']); ?>
  <?php if ($forced): ?>
    · ตั้งรหัสผ่านก่อนเริ่มใช้งาน ครั้งต่อไปให้เข้าสู่ระบบด้วยรหัสนักศึกษาและรหัสผ่านนี้
  <?php endif; ?>
</p>

<div class="card card-lg" style="max-width:520px">
  <form method="post" action="<?php echo e(url('account/set-password')); ?>" autocomplete="off">
    <?php echo csrf_field(); ?>

    <?php if (!$forced): ?>
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
    <?php endif; ?>

    <div class="field">
      <label class="label" for="new_password">รหัสผ่านใหม่</label>
      <div class="input-reveal">
        <input class="input" type="password" id="new_password" name="new_password"
               required minlength="8" autocomplete="new-password">
        <button type="button" class="reveal-btn" data-reveal-password="new_password"
                aria-controls="new_password" aria-pressed="false"
                aria-label="แสดงรหัสผ่าน" hidden>แสดง</button>
      </div>
      <div class="hint">
        อย่างน้อย 8 ตัวอักษร · ห้ามใช้เลขบัตรประชาชน รหัสนักศึกษา หรือเบอร์โทรศัพท์
        · แนะนำให้ใช้คำหรือวลีที่จำได้ง่ายแต่คนอื่นเดาไม่ได้
      </div>
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

    <button type="submit" class="btn btn-primary">บันทึกรหัสผ่าน</button>
  </form>
</div>

<p class="page-sub" style="max-width:520px;margin-top:18px">
  ลืมรหัสผ่าน? ขอรหัสเข้าใช้ครั้งแรกชุดใหม่จากครูที่ปรึกษา แล้วเลือก "เข้าใช้ครั้งแรก / ลืมรหัสผ่าน" ในหน้าเข้าสู่ระบบ
</p>
