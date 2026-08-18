<?php
/**
 * Institution sign-up request. Creates a school with status "pending" plus a
 * schooladmin user with status "pending"; the central admin approves both.
 *
 * @var array $old
 * @var array $errors
 */
$old = isset($old) ? $old : array();
$errors = isset($errors) ? $errors : array();

/**
 * @param array $errors
 * @param string $field
 * @return string
 */
$err = function ($field) use ($errors) {
    return isset($errors[$field])
        ? '<div class="field-error">' . e($errors[$field]) . '</div>'
        : '';
};
$cls = function ($field) use ($errors) {
    return isset($errors[$field]) ? ' has-error' : '';
};
?>
<div class="scr" style="min-height:100vh;background:var(--bg);padding:40px 24px">
  <div style="max-width:640px;margin:0 auto">

    <a class="btn" href="<?php echo e(url('home')); ?>" style="margin-bottom:24px">← กลับ</a>

    <div class="card card-lg">
      <h2 style="font-size:26px;font-weight:700;color:var(--text);margin-bottom:6px">
        สมัครใช้งานสำหรับสถานศึกษา
      </h2>
      <p style="color:var(--text-dim);font-size:14px;margin-bottom:28px">
        กรอกข้อมูลสถานศึกษา ผู้ดูแลระบบกลางจะตรวจสอบและเปิดใช้งานให้ภายใน 1-2 วันทำการ
      </p>

      <?php echo $this->partial('layout/flash'); ?>

      <form method="post" action="<?php echo e(url('register')); ?>">
        <?php echo csrf_field(); ?>

        <div class="grid-2">
          <div class="span-2">
            <label class="label" for="school_name">ชื่อสถานศึกษา *</label>
            <input class="input<?php echo $cls('school_name'); ?>" type="text" id="school_name"
                   name="school_name" required placeholder="เช่น วิทยาลัยเทคนิคเพชรบูรณ์"
                   value="<?php echo e(arr($old, 'school_name', '')); ?>">
            <?php echo $err('school_name'); ?>
          </div>

          <div>
            <label class="label" for="province">จังหวัด</label>
            <input class="input" type="text" id="province" name="province" placeholder="เพชรบูรณ์"
                   value="<?php echo e(arr($old, 'province', '')); ?>">
          </div>

          <div>
            <label class="label" for="affiliation">สังกัด</label>
            <input class="input" type="text" id="affiliation" name="affiliation" placeholder="สอศ."
                   value="<?php echo e(arr($old, 'affiliation', '')); ?>">
          </div>

          <div>
            <label class="label" for="contact_name">ชื่อผู้ประสานงาน *</label>
            <input class="input<?php echo $cls('contact_name'); ?>" type="text" id="contact_name"
                   name="contact_name" required placeholder="ชื่อ-นามสกุล"
                   value="<?php echo e(arr($old, 'contact_name', '')); ?>">
            <?php echo $err('contact_name'); ?>
          </div>

          <div>
            <label class="label" for="contact_phone">เบอร์โทรศัพท์</label>
            <input class="input" type="text" id="contact_phone" name="contact_phone"
                   placeholder="0xx-xxx-xxxx"
                   value="<?php echo e(arr($old, 'contact_phone', '')); ?>">
          </div>

          <div class="span-2">
            <label class="label" for="contact_email">อีเมลราชการ *</label>
            <input class="input<?php echo $cls('contact_email'); ?>" type="email" id="contact_email"
                   name="contact_email" required placeholder="contact@college.ac.th"
                   value="<?php echo e(arr($old, 'contact_email', '')); ?>">
            <div class="hint">อีเมลนี้จะกลายเป็นชื่อผู้ใช้ของผู้ดูแลระบบสถานศึกษา</div>
            <?php echo $err('contact_email'); ?>
          </div>

          <div>
            <label class="label" for="password">ตั้งรหัสผ่าน *</label>
            <input class="input<?php echo $cls('password'); ?>" type="password" id="password"
                   name="password" required minlength="8" autocomplete="new-password">
            <div class="hint">อย่างน้อย 8 ตัวอักษร</div>
            <?php echo $err('password'); ?>
          </div>

          <div>
            <label class="label" for="password_confirm">ยืนยันรหัสผ่าน *</label>
            <input class="input<?php echo $cls('password_confirm'); ?>" type="password"
                   id="password_confirm" name="password_confirm" required minlength="8"
                   autocomplete="new-password">
            <?php echo $err('password_confirm'); ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:24px">
          ส่งคำขอสมัครใช้งาน
        </button>
      </form>
    </div>

    <p style="text-align:center;font-size:13px;color:var(--text-dim);margin-top:18px">
      มีบัญชีอยู่แล้ว? <a href="<?php echo e(url('login', array('tab' => 'staff'))); ?>">เข้าสู่ระบบ</a>
    </p>

  </div>
</div>
