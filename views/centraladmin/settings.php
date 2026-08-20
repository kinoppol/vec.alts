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
        <input type="checkbox" name="allow_self_update" value="1" aria-label="เปิดให้ศิษย์เก่าแก้ไขข้อมูลของตนเอง"
               <?php echo arr($settings, 'allow_self_update', '1') === '1' ? 'checked' : ''; ?>>
        เปิดให้ศิษย์เก่าแก้ไขข้อมูลของตนเอง
      </label>
      <div class="hint">ถ้าปิด จะกรอกได้เฉพาะครูที่ปรึกษาและผู้ดูแลสถานศึกษา</div>
    </div>

    <div class="field">
      <label class="label">
        <input type="checkbox" name="allow_school_register" value="1" aria-label="เปิดให้สถานศึกษาสมัครใช้งานเองผ่านหน้าเว็บ"
               <?php echo arr($settings, 'allow_school_register', '1') === '1' ? 'checked' : ''; ?>>
        เปิดให้สถานศึกษาอื่นสมัครเข้าใช้งานเอง
      </label>
      <div class="hint">
        ถ้าปิด หน้าสมัครใช้งานจะแจ้งว่าปิดรับสมัคร และลิงก์สมัครจะถูกซ่อนทั้งเว็บ
        สถานศึกษาที่ใช้งานอยู่แล้วไม่ได้รับผลกระทบ
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--border);margin:24px 0">

    <h3 style="font-size:15px;font-weight:700;margin-bottom:6px">แหล่งข้อมูลผู้ใช้ภายนอก (RMS)</h3>
    <p class="hint" style="margin:0 0 14px">
      แต่ละสถานศึกษากำหนดที่อยู่ RMS ของตนเองได้ในข้อมูลสถานศึกษา
      ค่าตรงนี้เป็น <b>ค่าเริ่มต้น</b> ที่ใช้เมื่อสถานศึกษานั้นยังไม่ได้กำหนดไว้
      กรอกเฉพาะที่อยู่หลัก ส่วนพาธของ API ระบบกำหนดไว้ในโปรแกรมแล้ว
    </p>

    <div class="field">
      <label class="label" for="rms_base_url">ที่อยู่ระบบ RMS (ค่าเริ่มต้น)</label>
      <input class="input" type="url" id="rms_base_url" name="rms_base_url"
             placeholder="http://rms.pbntc.ac.th"
             value="<?php echo e(arr($settings, 'rms_base_url', '')); ?>">
      <div class="hint">
        ระบบจะเรียกข้อมูลจาก
        <code><?php echo e(arr($settings, 'rms_base_url', 'http://rms.pbntc.ac.th')); ?><?php echo e($rmsApiPath); ?></code>
        และดึงรูปโปรไฟล์จากพาธ <code>/files/</code> ของที่อยู่เดียวกัน
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--border);margin:24px 0">

    <h3 style="font-size:15px;font-weight:700;margin-bottom:6px">โหมดตรวจสอบข้อผิดพลาด</h3>
    <p class="hint" style="margin:0 0 14px">
      เปิดชั่วคราวเมื่อต้องการหาสาเหตุของปัญหา แล้ว<b>ปิดกลับทันทีเมื่อเสร็จ</b>
    </p>

    <div class="field">
      <label class="label">
        <input type="checkbox" name="app_debug" value="1"
               aria-label="เปิดโหมดตรวจสอบข้อผิดพลาด"
               <?php echo arr($settings, 'app_debug', '0') === '1' ? 'checked' : ''; ?>>
        เปิดโหมดตรวจสอบข้อผิดพลาด (Debug mode)
      </label>
      <?php if (arr($settings, 'app_debug', '0') === '1'): ?>
        <div class="alert alert-warn" style="margin-top:10px">
          <b>ขณะนี้เปิดอยู่</b> — เมื่อเกิดข้อผิดพลาด ระบบจะแสดงรายละเอียดทางเทคนิค
          เช่น ชื่อตารางและคอลัมน์ในฐานข้อมูล ให้ผู้ที่พบข้อผิดพลาดเห็น
          รวมถึงผู้เข้าชมทั่วไปที่ยังไม่ได้เข้าสู่ระบบ
          ไม่ควรเปิดค้างไว้บนเครื่องให้บริการจริง
        </div>
      <?php else: ?>
        <div class="hint">
          ปิดอยู่ — ผู้ใช้จะเห็นเพียงข้อความว่าเกิดข้อผิดพลาด
          ส่วนรายละเอียดถูกบันทึกไว้ในไฟล์ log ที่ <code>storage/logs/</code>
        </div>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary">บันทึกการตั้งค่า</button>
  </form>

  <p class="hint" style="margin-top:16px">
    สั่งโอนข้อมูลได้ที่เมนู <a href="<?php echo e(url('centraladmin/import-users')); ?>">โอนข้อมูลผู้ใช้</a>
  </p>
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
