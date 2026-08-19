<?php
/**
 * The current student's own screen: how to reach them, and what they mean to
 * do after finishing.
 *
 * Deliberately not the employment survey. That asks what someone is doing
 * now and only makes sense once they have graduated; this asks an intention,
 * and the two are counted separately so a plan never lands in the placement
 * rate.
 *
 * @var array $student the alumni row, with study_state = 'studying'
 */
$plans = graduation_plans();
$selected = (string) arr($student, 'plan_after', '');

$fullName = trim($student['title'] . $student['first_name'] . ' ' . $student['last_name']);
$initial = mb_substr(trim($student['first_name']) !== '' ? $student['first_name'] : $fullName, 0, 1);
?>

<div style="max-width:760px">

  <h1 class="page-title">ข้อมูลของฉัน</h1>
  <p class="page-sub">
    อัปเดตช่องทางติดต่อและความตั้งใจหลังสำเร็จการศึกษา
    เมื่อจบแล้วสถานศึกษาจะติดต่อคุณได้ทันทีโดยไม่ต้องตามหาใหม่
  </p>

  <div class="profile-card">
    <div class="avatar"><?php echo e($initial); ?></div>
    <div>
      <div style="font-weight:700;color:var(--text);font-size:16px"><?php echo e($fullName); ?></div>
      <div style="font-size:13px;color:var(--text-dim)">
        รหัส <?php echo e($student['student_code']); ?>
        <?php if ($student['level'] !== '' || $student['department_name'] !== null): ?>
          · <?php echo e(trim($student['level'] . ' ' . (string) $student['department_name'])); ?>
        <?php endif; ?>
        <?php if ((int) $student['graduation_year'] > 0): ?>
          · คาดว่าจบ <?php echo e($student['graduation_year']); ?>
        <?php endif; ?>
      </div>
    </div>
    <div style="margin-left:auto">
      <span class="badge badge-warn">กำลังศึกษา</span>
    </div>
  </div>

  <form method="post" action="<?php echo e(url('student')); ?>">
    <?php echo csrf_field(); ?>

    <div class="panel">
      <h3>ข้อมูลติดต่อ</h3>
      <div class="grid-2">
        <div>
          <label class="label" for="phone">เบอร์โทรศัพท์</label>
          <input class="input" type="text" id="phone" name="phone" placeholder="08x-xxx-xxxx"
                 value="<?php echo e($student['phone']); ?>">
        </div>
        <div>
          <label class="label" for="email">อีเมล</label>
          <input class="input" type="email" id="email" name="email" placeholder="อีเมลที่ติดต่อได้"
                 value="<?php echo e($student['email']); ?>">
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
      <h3 style="margin-bottom:6px">ความตั้งใจหลังสำเร็จการศึกษา</h3>
      <p class="panel-sub" style="margin-top:0">
        ยังเปลี่ยนได้ตลอด ตอบเท่าที่ตัดสินใจได้ตอนนี้ก็พอ
      </p>

      <div class="choice-grid">
        <?php foreach ($plans as $code => $info): ?>
          <label class="choice<?php echo $selected === $code ? ' on' : ''; ?>">
            <input type="radio" name="plan_after" value="<?php echo e($code); ?>"
                   aria-label="<?php echo e($info['label']); ?>"
                   <?php echo $selected === $code ? 'checked' : ''; ?>>
            <span class="emoji"><?php echo e($info['icon']); ?></span>
            <span><?php echo e($info['label']); ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:16px">
        <label class="label" for="plan_note">รายละเอียดเพิ่มเติม</label>
        <input class="input" type="text" id="plan_note" name="plan_note" maxlength="255"
               placeholder="เช่น อยากต่อ ป.ตรี สาขาไฟฟ้า หรือสนใจทำงานที่จังหวัดใด"
               value="<?php echo e(arr($student, 'plan_note', '')); ?>">
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">บันทึกข้อมูล</button>
  </form>

</div>
