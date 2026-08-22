<?php
/**
 * System settings + environment report.
 *
 * @var array $settings
 * @var array $env  runtime facts, useful when comparing XAMPP with the server
 * @var array $demo what the demo namespace currently holds
 * @var array $demoScale how much real data this installation carries
 * @var array|null $demoResult one-shot result of a seeding run, incl. password
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
      <div class="hint">แบบสำรวจที่ผู้สำเร็จการศึกษากรอกจะถูกบันทึกไว้ในปีนี้</div>
    </div>

    <div class="field">
      <label class="label">
        <input type="checkbox" name="allow_self_update" value="1" aria-label="เปิดให้ผู้สำเร็จการศึกษาแก้ไขข้อมูลของตนเอง"
               <?php echo arr($settings, 'allow_self_update', '1') === '1' ? 'checked' : ''; ?>>
        เปิดให้ผู้สำเร็จการศึกษาแก้ไขข้อมูลของตนเอง
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

<div class="card card-lg" style="max-width:640px;margin-bottom:22px">
  <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">ข้อมูลตัวอย่างสำหรับสาธิตและจับภาพหน้าจอ</h3>
  <p class="cell-dim" style="margin-bottom:18px">
    สร้างสถานศึกษาสมมติพร้อมผู้ใช้งานครบทุกบทบาท ไว้ใช้สาธิต อบรม
    หรือจับภาพหน้าจอประกอบคู่มือ โดยไม่ต้องใช้บัญชีและข้อมูลของบุคคลจริง
    ข้อมูลชุดนี้ตั้งชื่อกำกับว่า <b>(ข้อมูลทดสอบ)</b> ทุกแห่ง และลบออกได้ในคลิกเดียว
  </p>

  <?php if ($demoResult !== null): ?>
    <div class="alert alert-success" style="margin-bottom:18px">
      <b>สร้างข้อมูลตัวอย่างเรียบร้อยแล้ว</b>
      <p style="margin:10px 0 6px">
        รหัสผ่านของบัญชีตัวอย่างทุกบัญชี — <b>แสดงเพียงครั้งเดียว</b> โปรดคัดลอกไว้ก่อนออกจากหน้านี้
      </p>
      <p style="margin:0 0 14px">
        <code style="font-size:16px;font-weight:700;letter-spacing:.5px"><?php echo e($demoResult['password']); ?></code>
      </p>
      <table class="table" style="margin-bottom:12px">
        <thead><tr><th>บทบาท</th><th>อีเมลสำหรับเข้าสู่ระบบ</th></tr></thead>
        <tbody>
        <?php foreach ($demoResult['staff'] as $account): ?>
          <tr>
            <td><?php echo e($account['role']); ?></td>
            <td><code><?php echo e($account['login']); ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <table class="table">
        <thead><tr><th>บทบาท</th><th>รหัสนักศึกษา</th><th>เลขบัตรประชาชน (รหัสผ่าน)</th></tr></thead>
        <tbody>
        <?php foreach ($demoResult['learners'] as $account): ?>
          <tr>
            <td><?php echo e($account['role']); ?></td>
            <td><code><?php echo e($account['code']); ?></code></td>
            <td><code><?php echo e($account['idcard']); ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="hint" style="margin:12px 0 0">
        ผู้เรียนตัวอย่างคนอื่นใช้รหัสถัดไปเรียงกัน เข้าสู่ระบบผ่านแท็บผู้เรียนด้วยรหัสนักศึกษาและเลขบัตรประชาชน
      </p>
    </div>
  <?php endif; ?>

  <?php if ($demo['present']): ?>
    <dl class="kv" style="margin-bottom:18px">
      <dt>สถานศึกษาตัวอย่าง</dt><dd><?php echo e(number_format($demo['schools'])); ?> แห่ง</dd>
      <dt>ผู้ใช้งานตัวอย่าง</dt><dd><?php echo e(number_format($demo['users'])); ?> บัญชี</dd>
      <dt>ผู้สำเร็จการศึกษา</dt><dd><?php echo e(number_format($demo['graduates'])); ?> คน</dd>
      <dt>นักศึกษาปัจจุบัน</dt><dd><?php echo e(number_format($demo['students'])); ?> คน</dd>
      <dt>คำตอบแบบสำรวจ</dt><dd><?php echo e(number_format($demo['answers'])); ?> รายการ</dd>
    </dl>

    <form method="post" action="<?php echo e(url('centraladmin/demo-data')); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="task" value="purge">
      <button type="submit" class="btn btn-danger"
              data-confirm-title="ลบข้อมูลตัวอย่างทั้งหมด?"
              data-confirm-ok="ลบข้อมูลตัวอย่าง"
              data-confirm-danger
              data-confirm="สถานศึกษาที่กำกับว่าเป็นข้อมูลทดสอบ พร้อมผู้ใช้งานและข้อมูลผู้เรียนที่ผูกอยู่ จะถูกลบออกทั้งหมด&#10;ข้อมูลจริงของสถานศึกษาอื่นไม่ได้รับผลกระทบ">ลบข้อมูลตัวอย่างทั้งหมด</button>
      <div class="hint" style="margin-top:10px">
        ลบเฉพาะสถานศึกษาที่ระบบสร้างไว้เป็นข้อมูลทดสอบ พร้อมผู้ใช้งานและข้อมูลผู้เรียนที่ผูกอยู่กับสถานศึกษาเหล่านั้น
        ข้อมูลจริงของสถานศึกษาอื่นไม่ถูกแตะต้อง
      </div>
    </form>

  <?php else: ?>
    <?php if ($demoScale['production']): ?>
      <div class="alert alert-warn" style="margin-bottom:18px">
        <b>ฐานข้อมูลนี้มีข้อมูลจริงอยู่แล้ว</b> —
        ผู้เรียน <?php echo e(number_format($demoScale['alumni'])); ?> คน
        และผู้ใช้งาน <?php echo e(number_format($demoScale['users'])); ?> บัญชี
        <p style="margin:8px 0 0">
          ข้อมูลตัวอย่างจะถูกเก็บแยกและไม่ปะปนกับข้อมูลเหล่านี้ แต่จะปรากฏในหน้าสถานศึกษาทั้งหมดและรายงานภาพรวม
          หากต้องการทดลอง แนะนำให้ทำบนฐานข้อมูลสำเนา (staging) ก่อน
        </p>
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo e(url('centraladmin/demo-data')); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="task" value="seed">

      <?php if ($demoScale['production']): ?>
        <div class="field">
          <label class="label" for="confirm">พิมพ์ <b>DEMO</b> เพื่อยืนยัน</label>
          <input class="input" type="text" id="confirm" name="confirm" autocomplete="off"
                 placeholder="DEMO" style="max-width:200px">
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">สร้างข้อมูลตัวอย่าง</button>
      <div class="hint" style="margin-top:10px">
        ระบบจะสุ่มรหัสผ่านให้บัญชีตัวอย่างและแสดงบนหน้าจอครั้งเดียวหลังสร้างเสร็จ
      </div>
    </form>
  <?php endif; ?>
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
