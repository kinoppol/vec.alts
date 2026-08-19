<?php
/**
 * CSV import of the alumni roster.
 *
 * @var array|null $result  summary of the last run
 * @var array $departments
 */
$result = isset($result) ? $result : null;
?>
<h1 class="page-title">นำเข้าข้อมูลศิษย์เก่า</h1>
<p class="page-sub">อัปโหลดไฟล์ CSV รายชื่อผู้สำเร็จการศึกษา ระบบจะสร้างบัญชีให้ศิษย์เก่าเข้าใช้งานได้ทันที</p>

<?php if ($result !== null): ?>
  <div class="alert <?php echo $result['failed'] ? 'alert-warn' : 'alert-success'; ?>">
    นำเข้าสำเร็จ <?php echo e($result['created']); ?> รายการ ·
    ข้าม (มีอยู่แล้ว) <?php echo e($result['skipped']); ?> รายการ ·
    ผิดพลาด <?php echo e($result['failed']); ?> รายการ
  </div>
  <?php if ($result['errors']): ?>
    <div class="card" style="margin-bottom:20px">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">รายการที่ผิดพลาด</h3>
      <div class="sql-log"><?php foreach ($result['errors'] as $line) {
          echo e($line) . "\n";
      } ?></div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="card card-lg" style="max-width:720px;margin-bottom:20px">
  <form method="post" action="<?php echo e(url('schooladmin/import')); ?>" enctype="multipart/form-data"
        data-busy="กำลังนำเข้าข้อมูลศิษย์เก่า"
        data-busy-steps="ระบบกำลังอ่านไฟล์และสร้างบัญชีให้ศิษย์เก่าทีละราย ไฟล์ที่มีรายชื่อจำนวนมากจะใช้เวลานานขึ้น">
    <?php echo csrf_field(); ?>

    <div class="field">
      <label class="label" for="file">ไฟล์ CSV *</label>
      <input class="input" type="file" id="file" name="file" accept=".csv,text/csv" required>
      <div class="hint">ขนาดไม่เกิน 5 MB · เข้ารหัส UTF-8</div>
    </div>

    <div class="field">
      <label class="label" for="study_state">นำเข้าเป็นกลุ่มใด *</label>
      <select class="input" id="study_state" name="study_state" required>
        <option value="graduated">ศิษย์เก่า (สำเร็จการศึกษาแล้ว)</option>
        <option value="studying">ศิษย์ปัจจุบัน (กำลังศึกษา)</option>
      </select>
      <div class="hint">
        ศิษย์ปัจจุบันจะกรอกช่องทางติดต่อและความตั้งใจหลังจบไว้ล่วงหน้าได้
        แต่ยังไม่นับรวมในรายงานภาวะการมีงานทำ จนกว่าจะเปลี่ยนเป็นสำเร็จการศึกษา
      </div>
    </div>

    <div class="field">
      <label class="label" for="graduation_year">ปีที่สำเร็จการศึกษา / คาดว่าจะสำเร็จ (พ.ศ.) *</label>
      <input class="input" type="number" id="graduation_year" name="graduation_year"
             value="<?php echo e(current_academic_year()); ?>" min="2500" max="2700" required>
      <div class="hint">ใช้เมื่อไฟล์ไม่ได้ระบุปีไว้ในคอลัมน์</div>
    </div>

    <div class="field">
      <label class="label">
        <input type="checkbox" name="update_existing" value="1" aria-label="ปรับปรุงข้อมูลของรหัสนักศึกษาที่มีอยู่แล้ว">
        ปรับปรุงข้อมูลของรหัสนักศึกษาที่มีอยู่แล้ว
      </label>
      <div class="hint">ถ้าไม่เลือก ระบบจะข้ามแถวที่รหัสนักศึกษาซ้ำ</div>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">เริ่มนำเข้าข้อมูล</button>
  </form>
</div>

<div class="card" style="max-width:720px">
  <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">รูปแบบไฟล์</h3>
  <p class="cell-dim" style="margin-bottom:12px">
    บรรทัดแรกเป็นหัวคอลัมน์ ระบบอ่านชื่อคอลัมน์ต่อไปนี้ (คอลัมน์ที่มี * จำเป็นต้องมี)
  </p>
  <div class="sql-log">student_code*,national_id*,title,first_name*,last_name*,department,level,graduation_year,phone,email,line_id,address
6231010001,1234567890123,นาย,กิตติพงศ์,ใจดี,ช่างยนต์,ปวส.,2567,0812345678,kit@example.com,,เพชรบูรณ์
6231010007,1234567890124,น.ส.,ศิริพร,มั่นคง,ช่างยนต์,ปวส.,2567,,,,</div>
  <p class="hint" style="margin-top:12px">
    เลขบัตรประชาชนใช้เป็นรหัสผ่านของศิษย์เก่า ระบบจะเก็บไว้ในรูปแบบเข้ารหัสเท่านั้น
    ส่วนคอลัมน์ <b>department</b> ระบบจะจับคู่กับชื่อสาขาที่มีอยู่ ถ้าไม่พบจะสร้างใหม่ให้อัตโนมัติ
  </p>
  <p class="hint" style="margin-top:8px">
    ดาวน์โหลด <a href="<?php echo e(url('schooladmin/import', array('template' => 1))); ?>">ไฟล์ตัวอย่าง (CSV)</a>
  </p>
</div>
