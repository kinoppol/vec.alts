<?php
/**
 * CSV / RMS Excel import of the alumni roster.
 *
 * @var array|null $result  summary of the last run
 * @var array $departments
 */
$result = isset($result) ? $result : null;
?>
<h1 class="page-title">นำเข้าข้อมูลผู้สำเร็จการศึกษา</h1>
<p class="page-sub">
  อัปโหลดไฟล์ CSV ตามรูปแบบของระบบนี้ หรือไฟล์ Excel รายชื่อผู้จบการศึกษาที่ส่งออกจากระบบ RMS โดยตรง
  ระบบจะสร้างบัญชีให้ผู้สำเร็จการศึกษาเข้าใช้งานได้ทันที
</p>

<?php if ($result !== null): ?>
  <?php $isCheck = !empty($result['dry_run']); ?>
  <div class="alert <?php echo $result['failed'] ? 'alert-warn' : ($isCheck ? 'alert-info' : 'alert-success'); ?>">
    <?php if ($isCheck): ?>
      <b>ผลการตรวจสอบไฟล์ — ยังไม่ได้บันทึกลงฐานข้อมูล</b><br>
      จะเพิ่มใหม่ <?php echo e($result['created']); ?> รายการ ·
      จะปรับปรุงของเดิม <?php echo e($result['updated']); ?> รายการ ·
      จะข้าม (มีอยู่แล้ว) <?php echo e($result['skipped']); ?> รายการ ·
      ผิดพลาด <?php echo e($result['failed']); ?> รายการ
    <?php else: ?>
      นำเข้าใหม่ <?php echo e($result['created']); ?> รายการ ·
      ปรับปรุงของเดิม <?php echo e($result['updated']); ?> รายการ ·
      ข้าม (มีอยู่แล้ว) <?php echo e($result['skipped']); ?> รายการ ·
      ผิดพลาด <?php echo e($result['failed']); ?> รายการ
    <?php endif; ?>
  </div>

  <?php if ($isCheck && $result['new_departments']): ?>
    <div class="alert alert-info" style="margin-bottom:20px">
      สาขาที่ยังไม่มีในระบบ และจะถูกสร้างให้เมื่อนำเข้าจริง
      <?php echo e(count($result['new_departments'])); ?> สาขา —
      <?php echo e(implode(', ', $result['new_departments'])); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($result['not_graduated'])): ?>
    <div class="alert alert-info" style="margin-bottom:20px">
      ข้ามในไฟล์ Excel <?php echo e($result['not_graduated']); ?> รายการ
      เนื่องจากสถานะยังไม่ใช่ "สำเร็จการศึกษา"
    </div>
  <?php endif; ?>

  <?php if ($result['errors']): ?>
    <div class="card" style="margin-bottom:20px">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">รายการที่ผิดพลาด</h3>
      <div class="sql-log"><?php foreach ($result['errors'] as $line) {
          echo e($line) . "\n";
      } ?></div>
      <?php if ($isCheck): ?>
        <p class="hint" style="margin-top:12px">
          แก้ไขรายการเหล่านี้ในไฟล์แล้วตรวจสอบใหม่อีกครั้ง
          ถ้านำเข้าจริงทั้งที่ยังมีข้อผิดพลาด ระบบจะข้ามเฉพาะแถวที่ผิด และนำเข้าแถวที่เหลือตามปกติ
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="card card-lg" style="max-width:720px;margin-bottom:20px">
  <form method="post" action="<?php echo e(url('schooladmin/import')); ?>" enctype="multipart/form-data"
        data-busy="กำลังนำเข้าข้อมูลผู้สำเร็จการศึกษา"
        data-busy-steps="ระบบกำลังอ่านไฟล์และสร้างบัญชีให้ผู้สำเร็จการศึกษาทีละราย ไฟล์ที่มีรายชื่อจำนวนมากจะใช้เวลานานขึ้น">
    <?php echo csrf_field(); ?>

    <div class="field">
      <label class="label" for="file">ไฟล์ CSV หรือ Excel (.xlsx) *</label>
      <input class="input" type="file" id="file"
             name="file" accept=".csv,text/csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
             required>
      <div class="hint">
        ขนาดไม่เกิน 5 MB · ไฟล์ CSV เข้ารหัส UTF-8 ·
        ไฟล์ Excel ใช้รูปแบบรายงาน "รายชื่อนักเรียนที่จบการศึกษา" ที่ส่งออกจากระบบ RMS ได้ทันที ไม่ต้องแก้ไขคอลัมน์
      </div>
    </div>

    <div class="field">
      <label class="label" for="study_state">นำเข้าเป็นกลุ่มใด *</label>
      <select class="input" id="study_state" name="study_state" required>
        <option value="graduated">ผู้สำเร็จการศึกษา (จบแล้ว)</option>
        <option value="studying">ศิษย์ปัจจุบัน (ยังไม่จบ)</option>
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

    <button type="submit" name="mode" value="check" class="btn btn-block btn-lg"
            data-busy-message="กำลังตรวจสอบไฟล์"
            style="margin-bottom:10px">ตรวจสอบไฟล์ก่อน (ยังไม่บันทึก)</button>
    <button type="submit" name="mode" value="import" class="btn btn-primary btn-block btn-lg">เริ่มนำเข้าข้อมูล</button>
    <div class="hint" style="margin-top:10px">
      แนะนำให้กดตรวจสอบไฟล์ก่อนทุกครั้ง ระบบจะอ่านไฟล์ทั้งหมดและรายงานว่าจะเพิ่ม
      ปรับปรุง หรือข้ามกี่รายการ พร้อมรายการที่ผิดพลาด โดยไม่เขียนข้อมูลลงฐานข้อมูล
    </div>
  </form>
</div>

<div class="card" style="max-width:720px;margin-bottom:20px">
  <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">รูปแบบไฟล์ CSV</h3>
  <p class="cell-dim" style="margin-bottom:12px">
    บรรทัดแรกเป็นหัวคอลัมน์ ระบบอ่านชื่อคอลัมน์ต่อไปนี้ (คอลัมน์ที่มี * จำเป็นต้องมี)
  </p>
  <div class="sql-log">student_code*,national_id*,title,first_name*,last_name*,department,level,graduation_year,phone,email,line_id,address
6231010001,1234567890123,นาย,กิตติพงศ์,ใจดี,ช่างยนต์,ปวส.,2567,0812345678,kit@example.com,,เพชรบูรณ์
6231010007,1234567890124,น.ส.,ศิริพร,มั่นคง,ช่างยนต์,ปวส.,2567,,,,</div>
  <p class="hint" style="margin-top:12px">
    เลขบัตรประชาชนใช้เป็นรหัสผ่านของผู้สำเร็จการศึกษา ระบบจะเก็บไว้ในรูปแบบเข้ารหัสเท่านั้น
    ส่วนคอลัมน์ <b>department</b> ระบบจะจับคู่กับชื่อสาขาที่มีอยู่ ถ้าไม่พบจะสร้างใหม่ให้อัตโนมัติ
  </p>
  <p class="hint" style="margin-top:8px">
    ดาวน์โหลด <a href="<?php echo e(url('schooladmin/import', array('template' => 1))); ?>">ไฟล์ตัวอย่าง (CSV)</a>
  </p>
</div>

<div class="card" style="max-width:720px">
  <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">รูปแบบไฟล์ Excel จากระบบ RMS</h3>
  <p class="cell-dim">
    ใช้ไฟล์ Excel ที่ระบบ RMS ส่งออกเป็นรายงาน "รายชื่อนักเรียนที่จบการศึกษา" ได้โดยตรง
    (คอลัมน์: ลำดับ, เลขประจำตัวประชาชน, รหัสประจำตัว, รหัสกลุ่มเรียน, กลุ่มเรียน, ชื่อ-นามสกุล, ว.ด.ป. เกิด, สถานะนักเรียน, ผลการเรียน)
    ไม่ต้องแก้ไขหรือจัดคอลัมน์ใหม่ก่อนอัปโหลด
  </p>
  <ul class="hint" style="margin-top:12px;padding-left:18px;list-style:disc">
    <li>ระบบนำเข้าเฉพาะแถวที่คอลัมน์ <b>สถานะนักเรียน</b> เป็น "สำเร็จการศึกษา" เท่านั้น แถวอื่นจะถูกข้าม</li>
    <li>ระดับ (ปวช./ปวส.) อ่านจากคอลัมน์ <b>กลุ่มเรียน</b> โดยอัตโนมัติ</li>
    <li>
      ไฟล์นี้ไม่มีคอลัมน์สาขาวิชา รายการที่นำเข้าใหม่จะยังไม่ระบุสาขา ต้องไปกำหนดเพิ่มที่หน้า
      <a href="<?php echo e(url('schooladmin/alumni')); ?>">ข้อมูลผู้สำเร็จการศึกษา</a> ภายหลัง
    </li>
    <li>
      คนที่มีรหัสประจำตัวตรงกับรายการเดิมในระบบอยู่แล้ว (เช่น เคยโอนเข้ามาตอนยังเรียนอยู่จากเมนู
      "โอนข้อมูลนักเรียน") จะถูกปรับสถานะเป็นสำเร็จการศึกษาให้ทันทีเมื่อติ๊ก
      "ปรับปรุงข้อมูลของรหัสนักศึกษาที่มีอยู่แล้ว" — ต้องเลือก "ผู้สำเร็จการศึกษา (จบแล้ว)" ไว้ด้วย
      มิฉะนั้นระบบจะไม่เปลี่ยนสถานะคนที่มีอยู่แล้วให้
    </li>
  </ul>
</div>
