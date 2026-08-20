<?php
/**
 * An advisor updating one current student.
 *
 * Fields RMS owns are shown but not editable: the next transfer overwrites
 * them, so an edit here would quietly vanish.
 *
 * @var array $student
 * @var bool $fromRms  true when this record came from RMS
 */
$fullName = trim($student['title'] . $student['first_name'] . ' ' . $student['last_name']);
$plans = graduation_plans();
$currentPlan = (string) arr($student, 'plan_after', '');

$contactStates = array(
    'ok'          => 'ติดต่อได้',
    'hard'        => 'ติดต่อยาก',
    'unreachable' => 'ติดต่อไม่ได้',
);
$currentContact = (string) arr($student, 'contact_state', 'ok');
if (!isset($contactStates[$currentContact])) {
    $currentContact = 'ok';
}
?>
<a class="btn btn-sm" href="<?php echo e(url('advisor')); ?>" style="margin-bottom:16px">← กลับรายชื่อ</a>

<h1 class="page-title">ปรับปรุงข้อมูลนักศึกษา</h1>
<p class="page-sub">
  แก้ไขข้อมูลติดต่อและแผนหลังจบของนักศึกษาในความดูแล ทำได้ทุกเมื่อ ไม่ต้องรอถึงกำหนดสำรวจ
</p>

<div style="max-width:760px">

  <div class="profile-card">
    <?php echo $this->partial('layout/avatar', array(
        'name' => $fullName,
        'path' => '',
        'size' => 56,
    )); ?>
    <div>
      <div style="font-weight:700;color:var(--text);font-size:16px"><?php echo e($fullName); ?></div>
      <div style="font-size:13px;color:var(--text-dim)">
        รหัส <?php echo e($student['student_code']); ?>
        <?php if (trim((string) $student['group_name']) !== ''): ?>
          · <?php echo e($student['group_name']); ?>
        <?php endif; ?>
      </div>
    </div>
    <div style="margin-left:auto">
      <span class="badge badge-ok">กำลังศึกษา</span>
    </div>
  </div>

  <div class="panel">
    <h3 style="margin-bottom:6px">ข้อมูลจากระบบ RMS</h3>
    <p class="panel-sub" style="margin-top:0">
      <?php if ($fromRms): ?>
        ข้อมูลชุดนี้มาจากระบบ RMS จึงแก้ไขที่นี่ไม่ได้ — หากไม่ถูกต้องต้องแก้ที่ต้นทาง
        แล้วโอนข้อมูลใหม่ มิฉะนั้นการแก้ไขจะถูกเขียนทับในการโอนครั้งถัดไป
      <?php else: ?>
        ข้อมูลชุดนี้ไม่ได้มาจากระบบ RMS
      <?php endif; ?>
    </p>

    <dl class="kv">
      <dt>รหัสนักศึกษา</dt><dd><?php echo e($student['student_code']); ?></dd>
      <dt>ชื่อ-นามสกุล</dt><dd><?php echo e($fullName); ?></dd>
      <dt>ระดับชั้น</dt>
      <dd><?php echo e(trim($student['level'] . ' ' . (string) $student['grade_name'])) !== ''
            ? e(trim($student['level'] . ' · ' . (string) $student['grade_name'])) : '—'; ?></dd>
      <dt>สาขาวิชา</dt>
      <dd><?php echo e(trim((string) $student['major_name']) !== ''
            ? $student['major_name']
            : (string) $student['department_name']); ?></dd>
      <dt>กลุ่มเรียน</dt>
      <dd><?php echo e(trim((string) $student['group_code']) !== '' ? $student['group_code'] : '—'); ?></dd>
      <dt>ผลการเรียน (GPAX)</dt>
      <dd><?php echo e($student['gpax'] !== null ? $student['gpax'] : '—'); ?></dd>
      <?php if ((int) $student['entrance_year'] > 0): ?>
        <dt>เข้าศึกษา</dt>
        <dd>ภาค <?php echo e($student['entrance_semester'] . '/' . $student['entrance_year']); ?></dd>
      <?php endif; ?>
    </dl>
  </div>

  <form method="post" action="<?php echo e(url('advisor/student')); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo e($student['id']); ?>">

    <div class="panel">
      <h3>ข้อมูลติดต่อ</h3>
      <div class="grid-2">
        <div>
          <label class="label" for="phone">เบอร์โทรศัพท์</label>
          <?php if ($fromRms): ?>
            <div class="input" style="background:var(--surface-2);border-style:dashed">
              <?php echo e(trim((string) $student['phone']) !== '' ? $student['phone'] : '—'); ?>
            </div>
            <div class="hint">มาจากระบบ RMS แก้ไขไม่ได้</div>
          <?php else: ?>
            <input class="input" type="text" id="phone" name="phone" placeholder="08x-xxx-xxxx"
                   value="<?php echo e($student['phone']); ?>">
          <?php endif; ?>
        </div>

        <div>
          <label class="label" for="email">อีเมล</label>
          <?php if ($fromRms): ?>
            <div class="input" style="background:var(--surface-2);border-style:dashed">
              <?php echo e(trim((string) $student['email']) !== '' ? $student['email'] : '—'); ?>
            </div>
            <div class="hint">มาจากระบบ RMS แก้ไขไม่ได้</div>
          <?php else: ?>
            <input class="input" type="email" id="email" name="email" placeholder="อีเมลที่ติดต่อได้"
                   value="<?php echo e($student['email']); ?>">
          <?php endif; ?>
        </div>

        <div>
          <label class="label" for="line_id">Line ID</label>
          <input class="input" type="text" id="line_id" name="line_id" placeholder="ไลน์ไอดี"
                 value="<?php echo e($student['line_id']); ?>">
        </div>

        <div>
          <label class="label" for="address">ที่อยู่ปัจจุบัน</label>
          <input class="input" type="text" id="address" name="address"
                 placeholder="บ้านเลขที่ / ตำบล / อำเภอ / จังหวัด"
                 value="<?php echo e($student['address']); ?>">
        </div>
      </div>
    </div>

    <div class="panel">
      <h3 style="margin-bottom:6px">แผนหลังสำเร็จการศึกษา</h3>
      <p class="panel-sub" style="margin-top:0">
        บันทึกไว้ล่วงหน้าเพื่อให้ติดตามได้ตรงจุดเมื่อถึงเวลาสำรวจจริง
      </p>

      <div class="choice-grid">
        <?php foreach ($plans as $code => $plan): ?>
          <label class="choice<?php echo $currentPlan === $code ? ' on' : ''; ?>">
            <input type="radio" name="plan_after" value="<?php echo e($code); ?>"
                   aria-label="<?php echo e($plan['label']); ?>"
                   <?php echo $currentPlan === $code ? 'checked' : ''; ?>>
            <span class="emoji"><?php echo e($plan['icon']); ?></span>
            <span><?php echo e($plan['label']); ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="conditional">
        <label class="label" for="plan_note">รายละเอียดเพิ่มเติม</label>
        <input class="input" type="text" id="plan_note" name="plan_note"
               placeholder="เช่น ตั้งใจสอบเข้าที่ใด หรือสนใจงานสายใด"
               value="<?php echo e(arr($student, 'plan_note', '')); ?>">
      </div>
    </div>

    <div class="panel">
      <h3>บันทึกการติดต่อ</h3>
      <div class="grid-2">
        <div>
          <label class="label" for="contact_state">สถานะการติดต่อ</label>
          <select class="input" id="contact_state" name="contact_state">
            <?php foreach ($contactStates as $code => $label): ?>
              <option value="<?php echo e($code); ?>"
                      <?php echo $currentContact === $code ? 'selected' : ''; ?>>
                <?php echo e($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label" for="contact_note">หมายเหตุการติดต่อ</label>
          <input class="input" type="text" id="contact_note" name="contact_note"
                 placeholder="เช่น ติดต่อผ่านผู้ปกครอง"
                 value="<?php echo e(arr($student, 'contact_note', '')); ?>">
        </div>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn" href="<?php echo e(url('advisor')); ?>">ยกเลิก</a>
      <button type="submit" class="btn btn-primary btn-lg">บันทึกข้อมูล</button>
    </div>
  </form>

</div>
