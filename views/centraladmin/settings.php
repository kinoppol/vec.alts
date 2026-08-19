<?php
/**
 * System settings + environment report.
 *
 * @var array $settings
 * @var array $env  runtime facts, useful when comparing XAMPP with the server
 */
?>
<h1 class="page-title">ตั้งค่าระบบ</h1>
<p class="page-sub">ค่าที่ใช้ร่วมกันทุกสถานศึกษา และข้อมูลสภาพแวดล้อมของเครื่องที่ติดตั้ง</p>

<div class="card card-lg" style="max-width:640px;margin-bottom:22px">
  <h3 style="font-size:16px;font-weight:700;margin-bottom:18px">ค่าทั่วไป</h3>
  <form method="post" action="<?php echo e(url('centraladmin/settings')); ?>">
    <?php echo csrf_field(); ?>

    <div class="field">
      <label class="label" for="site_title">ชื่อระบบ</label>
      <input class="input" type="text" id="site_title" name="site_title"
             value="<?php echo e(arr($settings, 'site_title', '')); ?>">
    </div>

    <div class="field">
      <label class="label" for="survey_year">ปีสำรวจปัจจุบัน (พ.ศ.)</label>
      <input class="input" type="number" id="survey_year" name="survey_year" min="2500" max="2700"
             value="<?php echo e(arr($settings, 'survey_year', '')); ?>">
      <div class="hint">แบบสำรวจที่ศิษย์เก่ากรอกจะถูกบันทึกไว้ในปีนี้</div>
    </div>

    <div class="field">
      <label class="label">
        <input type="checkbox" name="allow_self_update" value="1"
               <?php echo arr($settings, 'allow_self_update', '1') === '1' ? 'checked' : ''; ?>>
        เปิดให้ศิษย์เก่าแก้ไขข้อมูลของตนเอง
      </label>
      <div class="hint">ถ้าปิด จะกรอกได้เฉพาะครูที่ปรึกษาและผู้ดูแลสถานศึกษา</div>
    </div>

    <div class="field">
      <label class="label">
        <input type="checkbox" name="allow_school_register" value="1"
               <?php echo arr($settings, 'allow_school_register', '1') === '1' ? 'checked' : ''; ?>>
        เปิดให้สถานศึกษาอื่นสมัครเข้าใช้งานเอง
      </label>
      <div class="hint">
        ถ้าปิด หน้าสมัครใช้งานจะแจ้งว่าปิดรับสมัคร และลิงก์สมัครจะถูกซ่อนทั้งเว็บ
        สถานศึกษาที่ใช้งานอยู่แล้วไม่ได้รับผลกระทบ
      </div>
    </div>

    <button type="submit" class="btn btn-primary">บันทึกการตั้งค่า</button>
  </form>
</div>

<div class="card card-lg" style="max-width:640px">
  <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">สภาพแวดล้อมการทำงาน</h3>
  <p class="cell-dim" style="margin-bottom:18px">
    ใช้ตรวจสอบว่าเครื่องทดสอบ (XAMPP) และเครื่องให้บริการจริง (CentOS 7) ตรงกันหรือไม่
  </p>
  <dl class="kv">
    <dt>PHP</dt><dd><?php echo e($env['php']); ?></dd>
    <dt>ฐานข้อมูล</dt><dd><?php echo e($env['db_flavour'] . ' ' . $env['db_version']); ?></dd>
    <dt>ชุดอักขระ</dt><dd><?php echo e($env['charset'] . ' / ' . $env['collation']); ?></dd>
    <dt>PDO driver</dt><dd><?php echo e($env['driver']); ?></dd>
    <dt>คำนำหน้าตาราง</dt><dd><?php echo e($env['prefix']); ?></dd>
    <dt>เขตเวลา</dt><dd><?php echo e($env['timezone']); ?></dd>
    <dt>Migration ล่าสุด</dt><dd><?php echo e($env['migration']); ?></dd>
    <dt>เวอร์ชันระบบ</dt><dd><?php echo e($env['app_version']); ?></dd>
  </dl>
  <p class="hint" style="margin-top:16px">
    จัดการโครงสร้างฐานข้อมูลได้ที่เมนู
    <a href="<?php echo e(url('admin/migrations')); ?>">Migration ฐานข้อมูล</a>
  </p>
</div>
