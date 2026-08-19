<?php
/**
 * My own profile. Linked from the sidebar of every staff role.
 *
 * Only the fields describing the person are editable here. Role, institution
 * and department decide what the account may reach, so they are shown as
 * facts and changed by an administrator instead.
 *
 * @var array $user        the signed-in staff row
 * @var array|null $school null for the central admin, who belongs to none
 */
?>
<h1 class="page-title">โปรไฟล์ของฉัน</h1>
<p class="page-sub">แก้ไขชื่อและช่องทางติดต่อของบัญชีคุณเอง</p>

<div class="card card-lg" style="max-width:520px;margin-bottom:22px">
  <form method="post" action="<?php echo e(url('account/profile')); ?>">
    <?php echo csrf_field(); ?>

    <div class="field">
      <label class="label" for="full_name">ชื่อ-นามสกุล *</label>
      <input class="input" type="text" id="full_name" name="full_name" required
             value="<?php echo e(arr($user, 'full_name', '')); ?>">
    </div>

    <div class="field">
      <label class="label" for="email">อีเมล *</label>
      <input class="input" type="email" id="email" name="email" required
             value="<?php echo e(arr($user, 'email', '')); ?>">
      <div class="hint">อีเมลนี้ใช้เข้าสู่ระบบด้วย ถ้าเปลี่ยนแล้วต้องใช้อีเมลใหม่ในการล็อกอินครั้งถัดไป</div>
    </div>

    <div class="field">
      <label class="label" for="phone">เบอร์โทรศัพท์</label>
      <input class="input" type="text" id="phone" name="phone" inputmode="tel"
             value="<?php echo e(arr($user, 'phone', '')); ?>">
    </div>

    <button type="submit" class="btn btn-primary">บันทึกโปรไฟล์</button>
  </form>
</div>

<div class="card card-lg" style="max-width:520px">
  <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">ข้อมูลบัญชี</h3>
  <p class="cell-dim" style="margin-bottom:18px">
    ส่วนนี้แก้ไขเองไม่ได้ ต้องให้ผู้ดูแลเป็นผู้เปลี่ยนให้
  </p>
  <dl class="kv">
    <dt>บทบาท</dt>
    <dd><?php echo e(role_label(arr($user, 'role', ''))); ?></dd>

    <dt>สถานศึกษา</dt>
    <dd><?php echo $school === null ? 'ส่วนกลาง (ไม่สังกัดสถานศึกษา)' : e($school['name']); ?></dd>

    <dt>สถานะบัญชี</dt>
    <dd><?php echo arr($user, 'status', '') === 'active' ? 'ใช้งานได้' : 'ถูกระงับ'; ?></dd>

    <dt>เข้าสู่ระบบล่าสุด</dt>
    <dd><?php echo e(thai_date(arr($user, 'last_login_at', null))); ?></dd>
  </dl>
  <p class="hint" style="margin-top:18px">
    ต้องการเปลี่ยนรหัสผ่าน? ไปที่
    <a href="<?php echo e(url('account/password')); ?>">เปลี่ยนรหัสผ่าน</a>
  </p>
</div>
