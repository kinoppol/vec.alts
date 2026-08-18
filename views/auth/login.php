<?php
/**
 * Split-screen sign in. Two tabs: alumni (student code + national ID) and
 * staff (email + password).
 *
 * @var string $tab      'alumni' or 'staff'
 * @var array $old       previously submitted values
 * @var string $error
 */
$tab = isset($tab) ? $tab : 'alumni';
$old = isset($old) ? $old : array();
?>
<div class="auth scr">

  <div class="auth-side">
    <a class="auth-back" href="<?php echo e(url('home')); ?>">← กลับหน้าแรก</a>
    <div>
      <div class="auth-mark">ศ</div>
      <h2>ยินดีต้อนรับกลับ<br>สู่ระบบติดตามศิษย์เก่า</h2>
      <p>กรอกข้อมูลของคุณเพื่อช่วยพัฒนาการเรียนการสอนให้รุ่นน้อง
         และช่วยให้สถานศึกษาติดตามผลได้อย่างแม่นยำ</p>
    </div>
    <div style="font-size:13px;opacity:.8">
      ต้องการเพิ่มสถานศึกษา? <a href="<?php echo e(url('register')); ?>">สมัครใช้งาน</a>
    </div>
  </div>

  <div class="auth-main">
    <div class="auth-box">

      <div class="tabs">
        <a class="tab<?php echo $tab === 'alumni' ? ' on' : ''; ?>"
           href="<?php echo e(url('login', array('tab' => 'alumni'))); ?>">ศิษย์เก่า</a>
        <a class="tab<?php echo $tab === 'staff' ? ' on' : ''; ?>"
           href="<?php echo e(url('login', array('tab' => 'staff'))); ?>">บุคลากร</a>
      </div>

      <h3 style="font-size:22px;font-weight:700;color:var(--text);margin-bottom:20px">เข้าสู่ระบบ</h3>

      <?php echo $this->partial('layout/flash'); ?>

      <?php if ($tab === 'alumni'): ?>
        <form method="post" action="<?php echo e(url('login')); ?>" autocomplete="on">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="tab" value="alumni">

          <div class="field">
            <label class="label" for="student_code">รหัสนักศึกษา (รหัสเดิมตอนเรียน)</label>
            <input class="input" type="text" id="student_code" name="student_code"
                   inputmode="numeric" placeholder="เช่น 6231010001" required
                   value="<?php echo e(arr($old, 'student_code', '')); ?>">
          </div>

          <div class="field">
            <label class="label" for="national_id">เลขบัตรประชาชน (รหัสผ่าน)</label>
            <input class="input" type="password" id="national_id" name="national_id"
                   inputmode="numeric" placeholder="เลขบัตรประชาชน 13 หลัก" required>
            <div class="hint">ระบบเก็บเลขบัตรในรูปแบบเข้ารหัส ไม่สามารถอ่านย้อนกลับได้</div>
          </div>

          <button type="submit" class="btn btn-primary btn-block" style="padding:14px;border-radius:11px;font-size:15px">
            เข้าสู่ระบบ
          </button>
        </form>

      <?php else: ?>
        <form method="post" action="<?php echo e(url('login')); ?>" autocomplete="on">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="tab" value="staff">

          <div class="field">
            <label class="label" for="identifier">อีเมล / ชื่อผู้ใช้</label>
            <input class="input" type="text" id="identifier" name="identifier"
                   placeholder="name@college.ac.th" required
                   value="<?php echo e(arr($old, 'identifier', '')); ?>">
          </div>

          <div class="field">
            <label class="label" for="password">รหัสผ่าน</label>
            <input class="input" type="password" id="password" name="password"
                   placeholder="••••••••" required autocomplete="current-password">
          </div>

          <div class="hint" style="margin-bottom:16px">
            ระบบจะพาไปยังหน้าจอตามบทบาทของบัญชีโดยอัตโนมัติ
          </div>

          <button type="submit" class="btn btn-primary btn-block" style="padding:14px;border-radius:11px;font-size:15px">
            เข้าสู่ระบบ
          </button>
        </form>
      <?php endif; ?>

      <p style="text-align:center;font-size:13px;color:var(--text-dim);margin-top:18px">
        ยังไม่มีบัญชีสถานศึกษา? <a href="<?php echo e(url('register')); ?>">สมัครใช้งาน</a>
      </p>

    </div>
  </div>
</div>
