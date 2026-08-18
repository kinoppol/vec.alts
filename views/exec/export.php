<?php
/**
 * Export options. The download itself is streamed by the controller.
 *
 * @var array $gradYears
 * @var int $year
 */
?>
<h1 class="page-title">ส่งออกรายงาน</h1>
<p class="page-sub">ดาวน์โหลดข้อมูลเป็นไฟล์ CSV เพื่อนำไปจัดทำรายงานส่งต้นสังกัด</p>

<div class="card card-lg" style="max-width:640px">
  <form method="get" action="<?php echo e(url()); ?>">
    <input type="hidden" name="r" value="exec/export">
    <input type="hidden" name="download" value="1">

    <div class="field">
      <label class="label" for="grad_year">ปีที่สำเร็จการศึกษา</label>
      <select class="input" id="grad_year" name="grad_year">
        <option value="0">ทุกปี</option>
        <?php foreach ($gradYears as $y): ?>
          <option value="<?php echo e($y); ?>"><?php echo e($y); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label class="label" for="survey_year">ปีสำรวจ</label>
      <input class="input" type="number" id="survey_year" name="survey_year"
             value="<?php echo e($year); ?>" min="2500" max="2700">
    </div>

    <div class="field">
      <label class="label" for="scope">ขอบเขตข้อมูล</label>
      <select class="input" id="scope" name="scope">
        <option value="answered">เฉพาะผู้ที่ตอบแบบสำรวจแล้ว</option>
        <option value="all">ศิษย์เก่าทั้งหมด (รวมผู้ที่ยังไม่ตอบ)</option>
      </select>
    </div>

    <div class="alert alert-info" style="margin-top:8px">
      ไฟล์ CSV จะมี BOM ของ UTF-8 กำกับไว้ เพื่อให้ Microsoft Excel เปิดภาษาไทยได้ถูกต้อง
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">ดาวน์โหลดไฟล์ CSV</button>
  </form>
</div>
