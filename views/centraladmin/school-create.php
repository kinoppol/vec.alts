<?php
/**
 * Add an institution by hand.
 *
 * @var array $old
 * @var array $errors
 * @var string $defaultRmsUrl
 */
$old = isset($old) ? $old : array();
$errors = isset($errors) ? $errors : array();

$err = function ($field) use ($errors) {
    return isset($errors[$field])
        ? '<div class="field-error">' . e($errors[$field]) . '</div>'
        : '';
};
$cls = function ($field) use ($errors) {
    return isset($errors[$field]) ? ' has-error' : '';
};
$withAdmin = !empty($old['with_admin']);
?>
<a class="btn btn-sm" href="<?php echo e(url('centraladmin')); ?>" style="margin-bottom:16px">← กลับ</a>

<h1 class="page-title">เพิ่มสถานศึกษา</h1>
<p class="page-sub">
  บันทึกสถานศึกษาที่จะใช้งานระบบเข้ามาเอง โดยไม่ต้องรอให้สมัครผ่านหน้าเว็บ
</p>

<form method="post" action="<?php echo e(url('centraladmin/school-create')); ?>">
  <?php echo csrf_field(); ?>

  <div class="card card-lg" style="max-width:760px;margin-bottom:20px">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:18px">ข้อมูลสถานศึกษา</h3>

    <div class="grid-2">
      <div class="span-2">
        <label class="label" for="name">ชื่อสถานศึกษา *</label>
        <input class="input<?php echo $cls('name'); ?>" type="text" id="name" name="name" required
               placeholder="เช่น วิทยาลัยเทคนิคเพชรบูรณ์"
               value="<?php echo e(arr($old, 'name', '')); ?>">
        <?php echo $err('name'); ?>
      </div>

      <div>
        <label class="label" for="code">รหัสสถานศึกษา</label>
        <input class="input" type="text" id="code" name="code" placeholder="ไม่บังคับ"
               value="<?php echo e(arr($old, 'code', '')); ?>">
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
        <label class="label" for="status">สถานะการใช้งาน</label>
        <select class="input" id="status" name="status">
          <?php
          $statuses = array(
              'active'    => 'ใช้งาน — เข้าระบบได้ทันที',
              'pending'   => 'รออนุมัติ — ยังเข้าระบบไม่ได้',
              'suspended' => 'ระงับ',
          );
          $current = arr($old, 'status', 'active');
          foreach ($statuses as $code => $label): ?>
            <option value="<?php echo e($code); ?>" <?php echo $current === $code ? 'selected' : ''; ?>>
              <?php echo e($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="span-2">
        <label class="label" for="note">หมายเหตุ</label>
        <input class="input" type="text" id="note" name="note" placeholder="ไม่บังคับ"
               value="<?php echo e(arr($old, 'note', '')); ?>">
      </div>
    </div>
  </div>

  <div class="card card-lg" style="max-width:760px;margin-bottom:20px">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">ที่อยู่ระบบ RMS ของสถานศึกษานี้</h3>
    <p class="hint" style="margin:0 0 14px">
      ใช้เป็นแหล่งโอนข้อมูลผู้ใช้ของสถานศึกษาแห่งนี้โดยเฉพาะ
      กรอกเฉพาะที่อยู่หลัก ส่วนพาธของ API ระบบกำหนดไว้ในโปรแกรมแล้ว
    </p>

    <div class="field">
      <label class="label" for="rms_base_url">ที่อยู่ระบบ RMS</label>
      <input class="input<?php echo $cls('rms_base_url'); ?>" type="url" id="rms_base_url"
             name="rms_base_url" placeholder="<?php echo e($defaultRmsUrl !== '' ? $defaultRmsUrl : 'http://rms.example.ac.th'); ?>"
             value="<?php echo e(arr($old, 'rms_base_url', '')); ?>">
      <div class="hint">
        <?php if ($defaultRmsUrl !== ''): ?>
          เว้นว่างไว้ได้ ระบบจะใช้ค่าเริ่มต้นจากเมนูตั้งค่าระบบ
          (<code><?php echo e($defaultRmsUrl); ?></code>)
        <?php else: ?>
          ยังไม่ได้ตั้งค่าเริ่มต้นไว้ที่เมนูตั้งค่าระบบ หากเว้นว่างจะโอนข้อมูลผู้ใช้ไม่ได้
        <?php endif; ?>
      </div>
      <?php echo $err('rms_base_url'); ?>
    </div>
  </div>

  <div class="card card-lg" style="max-width:760px;margin-bottom:20px">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:18px">ผู้ประสานงาน</h3>
    <div class="grid-2">
      <div>
        <label class="label" for="contact_name">ชื่อผู้ประสานงาน</label>
        <input class="input" type="text" id="contact_name" name="contact_name" placeholder="ชื่อ-นามสกุล"
               value="<?php echo e(arr($old, 'contact_name', '')); ?>">
      </div>
      <div>
        <label class="label" for="contact_phone">เบอร์โทรศัพท์</label>
        <input class="input" type="text" id="contact_phone" name="contact_phone" placeholder="0xx-xxx-xxxx"
               value="<?php echo e(arr($old, 'contact_phone', '')); ?>">
      </div>
      <div class="span-2">
        <label class="label" for="contact_email">อีเมลติดต่อ</label>
        <input class="input<?php echo $cls('contact_email'); ?>" type="email" id="contact_email"
               name="contact_email" placeholder="contact@college.ac.th"
               value="<?php echo e(arr($old, 'contact_email', '')); ?>">
        <?php echo $err('contact_email'); ?>
      </div>
    </div>
  </div>

  <div class="card card-lg" style="max-width:760px;margin-bottom:20px">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">บัญชีผู้ดูแลสถานศึกษา</h3>
    <p class="hint" style="margin:0 0 14px">
      สถานศึกษาที่ไม่มีผู้ดูแลจะไม่มีใครจัดการผู้ใช้หรือนำเข้าข้อมูลได้
      สร้างพร้อมกันตรงนี้ได้เลย หรือจะเพิ่มภายหลังก็ได้
    </p>

    <div class="field">
      <label class="label">
        <input type="checkbox" name="with_admin" value="1" aria-label="สร้างบัญชีผู้ดูแลสถานศึกษาพร้อมกัน" <?php echo $withAdmin ? 'checked' : ''; ?>>
        สร้างบัญชีผู้ดูแลสถานศึกษาพร้อมกัน
      </label>
    </div>

    <div class="grid-2">
      <div>
        <label class="label" for="admin_name">ชื่อ-นามสกุล</label>
        <input class="input<?php echo $cls('admin_name'); ?>" type="text" id="admin_name"
               name="admin_name" value="<?php echo e(arr($old, 'admin_name', '')); ?>">
        <?php echo $err('admin_name'); ?>
      </div>
      <div>
        <label class="label" for="admin_email">อีเมล (ใช้เข้าสู่ระบบ)</label>
        <input class="input<?php echo $cls('admin_email'); ?>" type="email" id="admin_email"
               name="admin_email" autocomplete="off"
               value="<?php echo e(arr($old, 'admin_email', '')); ?>">
        <?php echo $err('admin_email'); ?>
      </div>
      <div class="span-2">
        <label class="label" for="admin_password">รหัสผ่านเริ่มต้น</label>
        <input class="input<?php echo $cls('admin_password'); ?>" type="text" id="admin_password"
               name="admin_password" minlength="8" autocomplete="off">
        <div class="hint">อย่างน้อย 8 ตัวอักษร แจ้งให้ผู้ดูแลเปลี่ยนเมื่อเข้าใช้ครั้งแรก</div>
        <?php echo $err('admin_password'); ?>
      </div>
    </div>
  </div>

  <div class="form-actions" style="max-width:760px">
    <a class="btn" href="<?php echo e(url('centraladmin')); ?>">ยกเลิก</a>
    <button type="submit" class="btn btn-primary btn-lg">บันทึกสถานศึกษา</button>
  </div>
</form>
