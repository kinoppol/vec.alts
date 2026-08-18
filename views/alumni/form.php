<?php
/**
 * The survey form. Used both by an alumnus filling it in for themselves and
 * by an advisor filling it in on their behalf.
 *
 * @var array $alumni
 * @var array|null $status  existing answer for $year
 * @var int $year
 * @var bool $onBehalf      true when an advisor is filling this in
 */
$status = isset($status) ? $status : null;
$statuses = employment_statuses();
$selected = $status ? (string) $status['employment_status'] : '';
$selectedGroup = isset($statuses[$selected]) ? $statuses[$selected]['group'] : '';

$fullName = trim($alumni['title'] . $alumni['first_name'] . ' ' . $alumni['last_name']);
$initial = mb_substr(trim($alumni['first_name']) !== '' ? $alumni['first_name'] : $fullName, 0, 1);

if ($status === null) {
    $stateBadge = array('wait', 'ยังไม่อัปเดต');
} elseif ((int) $status['is_draft'] === 1) {
    $stateBadge = array('warn', 'บันทึกร่างไว้');
} else {
    $stateBadge = array('done', 'อัปเดตแล้ว');
}

/** @return string */
$val = function ($key, $default = '') use ($status) {
    if ($status === null || !isset($status[$key]) || $status[$key] === null) {
        return $default;
    }
    return (string) $status[$key];
};
?>

<div style="max-width:760px">

  <?php if ($onBehalf): ?>
    <a class="btn btn-sm" href="<?php echo e(url('advisor')); ?>" style="margin-bottom:16px">← กลับรายชื่อ</a>
  <?php endif; ?>

  <h1 class="page-title"><?php echo $onBehalf ? 'กรอกข้อมูลแทนศิษย์เก่า' : 'ข้อมูลศิษย์เก่า'; ?></h1>
  <p class="page-sub">
    <?php if ($onBehalf): ?>
      บันทึกข้อมูลแทนศิษย์เก่าที่ติดต่อได้ · ปีสำรวจ <?php echo e($year); ?>
    <?php else: ?>
      อัปเดตข้อมูลส่วนตัวและสถานะปัจจุบันของคุณ เพื่อให้สถานศึกษาติดตามและช่วยเหลือได้ · ปีสำรวจ <?php echo e($year); ?>
    <?php endif; ?>
  </p>

  <div class="profile-card">
    <div class="avatar"><?php echo e($initial); ?></div>
    <div>
      <div style="font-weight:700;color:var(--text);font-size:16px"><?php echo e($fullName); ?></div>
      <div style="font-size:13px;color:var(--text-dim)">
        รหัส <?php echo e($alumni['student_code']); ?>
        <?php if ($alumni['level'] !== '' || $alumni['department_name'] !== null): ?>
          · <?php echo e(trim($alumni['level'] . ' ' . (string) $alumni['department_name'])); ?>
        <?php endif; ?>
        <?php if ((int) $alumni['graduation_year'] > 0): ?>
          · สำเร็จ <?php echo e($alumni['graduation_year']); ?>
        <?php endif; ?>
      </div>
    </div>
    <div style="margin-left:auto">
      <span class="badge badge-<?php echo e($stateBadge[0]); ?>"><?php echo e($stateBadge[1]); ?></span>
    </div>
  </div>

  <form method="post" data-survey-form
        action="<?php echo e($onBehalf ? url('advisor/fill', array('id' => $alumni['id'])) : url('alumni')); ?>">
    <?php echo csrf_field(); ?>

    <div class="panel">
      <h3>ข้อมูลติดต่อ</h3>
      <div class="grid-2">
        <div>
          <label class="label" for="phone">เบอร์โทรศัพท์</label>
          <input class="input" type="text" id="phone" name="phone" placeholder="08x-xxx-xxxx"
                 value="<?php echo e($alumni['phone']); ?>">
        </div>
        <div>
          <label class="label" for="email">อีเมล</label>
          <input class="input" type="email" id="email" name="email" placeholder="อีเมลที่ติดต่อได้"
                 value="<?php echo e($alumni['email']); ?>">
        </div>
        <div>
          <label class="label" for="line_id">Line ID</label>
          <input class="input" type="text" id="line_id" name="line_id" placeholder="ไลน์ไอดี"
                 value="<?php echo e($alumni['line_id']); ?>">
        </div>
        <div>
          <label class="label" for="address">ที่อยู่ปัจจุบัน</label>
          <input class="input" type="text" id="address" name="address"
                 placeholder="บ้านเลขที่ / ตำบล / อำเภอ / จังหวัด"
                 value="<?php echo e($alumni['address']); ?>">
        </div>
      </div>
    </div>

    <div class="panel">
      <h3 style="margin-bottom:6px">สถานะปัจจุบัน</h3>
      <p class="panel-sub" style="margin-top:0">หลังสำเร็จการศึกษา ขณะนี้อยู่ในสถานะใด</p>

      <div class="choice-grid">
        <?php foreach ($statuses as $code => $info): ?>
          <label class="choice<?php echo $selected === $code ? ' on' : ''; ?>">
            <input type="radio" name="employment_status" value="<?php echo e($code); ?>"
                   data-group="<?php echo e($info['group']); ?>"
                   aria-label="<?php echo e($info['label']); ?>"
                   <?php echo $selected === $code ? 'checked' : ''; ?>>
            <span class="emoji"><?php echo e($info['icon']); ?></span>
            <span><?php echo e($info['label']); ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- Employment details -->
      <div class="conditional" data-emp-group="job"
           style="<?php echo $selectedGroup === 'job' ? '' : 'display:none'; ?>">
        <div class="grid-2">
          <div>
            <label class="label" for="company_name">ชื่อสถานประกอบการ</label>
            <input class="input" type="text" id="company_name" name="company_name"
                   placeholder="ชื่อบริษัท/ร้าน" value="<?php echo e($val('company_name')); ?>">
          </div>
          <div>
            <label class="label" for="job_position">ตำแหน่งงาน</label>
            <input class="input" type="text" id="job_position" name="job_position"
                   placeholder="เช่น ช่างซ่อมบำรุง" value="<?php echo e($val('job_position')); ?>">
          </div>
          <div>
            <label class="label" for="salary">เงินเดือน (บาท)</label>
            <input class="input" type="number" id="salary" name="salary" min="0" step="500"
                   placeholder="เช่น 15000"
                   value="<?php echo e($val('salary') === '' ? '' : (int) $val('salary')); ?>">
          </div>
          <div>
            <label class="label" for="work_province">จังหวัดที่ทำงาน</label>
            <input class="input" type="text" id="work_province" name="work_province"
                   placeholder="จังหวัด" value="<?php echo e($val('work_province')); ?>">
          </div>
        </div>
      </div>

      <!-- Further study details -->
      <div class="conditional" data-emp-group="study"
           style="<?php echo $selectedGroup === 'study' ? '' : 'display:none'; ?>">
        <div class="grid-2">
          <div class="span-2">
            <label class="label" for="study_place">สถานศึกษาที่ศึกษาต่อ</label>
            <input class="input" type="text" id="study_place" name="study_place"
                   placeholder="ชื่อมหาวิทยาลัย/สถาบัน" value="<?php echo e($val('study_place')); ?>">
          </div>
          <div>
            <label class="label" for="study_level">ระดับ</label>
            <input class="input" type="text" id="study_level" name="study_level"
                   placeholder="เช่น ปริญญาตรี" value="<?php echo e($val('study_level')); ?>">
          </div>
          <div>
            <label class="label" for="study_major">สาขาวิชา</label>
            <input class="input" type="text" id="study_major" name="study_major"
                   placeholder="สาขาที่ศึกษาต่อ" value="<?php echo e($val('study_major')); ?>">
          </div>
        </div>
      </div>

      <!-- Notes for the remaining statuses -->
      <div class="conditional" data-emp-group="note"
           style="<?php echo $selectedGroup === 'note' ? '' : 'display:none'; ?>">
        <label class="label" for="note">รายละเอียดเพิ่มเติม</label>
        <textarea class="input" id="note" name="note"
                  placeholder="เช่น กำลังหางานในสายงานใด หรือหมายเหตุอื่น ๆ"><?php echo e($val('note')); ?></textarea>
      </div>
    </div>

    <?php if ($onBehalf): ?>
      <div class="panel">
        <h3>บันทึกการติดต่อ</h3>
        <div class="grid-2">
          <div>
            <label class="label" for="contact_state">สถานะการติดต่อ</label>
            <select class="input" id="contact_state" name="contact_state">
              <?php
              $contactStates = array(
                  'ok'          => 'ติดต่อได้',
                  'hard'        => 'ติดต่อยาก',
                  'unreachable' => 'ติดต่อไม่ได้',
              );
              $currentContact = isset($alumni['contact_state']) ? $alumni['contact_state'] : 'ok';
              foreach ($contactStates as $code => $label): ?>
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
                   value="<?php echo e(isset($alumni['contact_note']) ? $alumni['contact_note'] : ''); ?>">
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <button type="submit" name="action" value="draft" class="btn">บันทึกร่าง</button>
      <button type="submit" name="action" value="submit" class="btn btn-primary btn-lg">
        บันทึกและส่งข้อมูล
      </button>
    </div>
  </form>

</div>
