<?php
/**
 * Sign in, in two steps: pick the kind of account, then fill in the matching
 * form.
 *
 * The step exists because the two kinds take completely different
 * credentials. A single screen that opened on one of them had staff typing
 * their email address into the field asking for a student code.
 *
 * @var string $tab '' when nothing is chosen yet, otherwise 'alumni' or 'staff'
 * @var array $old  previously submitted values
 */
$tab = isset($tab) ? $tab : '';
$old = isset($old) ? $old : array();

$kinds = array(
    'alumni' => array(
        'icon'  => '🎓',
        'label' => 'ศิษย์เก่า',
        'desc'  => 'ผู้สำเร็จการศึกษา · ใช้รหัสนักศึกษาและเลขบัตรประชาชน',
    ),
    'staff' => array(
        'icon'  => '🏫',
        'label' => 'บุคลากร',
        'desc'  => 'ครูที่ปรึกษา ผู้บริหาร ผู้ดูแลระบบ · ใช้อีเมลและรหัสผ่าน',
    ),
);
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
    <?php if ($repo->registrationOpen()): ?>
      <div style="font-size:13px;opacity:.8">
        ต้องการเพิ่มสถานศึกษา? <a href="<?php echo e(url('register')); ?>">สมัครใช้งาน</a>
      </div>
    <?php else: ?>
      <div></div>
    <?php endif; ?>
  </div>

  <div class="auth-main">
    <div class="auth-box">

      <?php if ($tab === ''): ?>

        <h3 class="auth-title">เข้าสู่ระบบ</h3>
        <p class="auth-lead">เลือกประเภทผู้ใช้งานของคุณก่อน ระบบจะได้ถามข้อมูลให้ถูกต้อง</p>

        <?php echo $this->partial('layout/flash'); ?>

        <div class="choice-list">
          <?php foreach ($kinds as $key => $kind): ?>
            <a class="choice" href="<?php echo e(url('login', array('tab' => $key))); ?>">
              <span class="choice-icon" aria-hidden="true"><?php echo $kind['icon']; ?></span>
              <span class="choice-text">
                <span class="choice-title"><?php echo e($kind['label']); ?></span>
                <span class="choice-desc"><?php echo e($kind['desc']); ?></span>
              </span>
              <span class="choice-go" aria-hidden="true">→</span>
            </a>
          <?php endforeach; ?>
        </div>

      <?php else: ?>

        <a class="auth-change" href="<?php echo e(url('login')); ?>">← เปลี่ยนประเภทผู้ใช้งาน</a>

        <h3 class="auth-title">
          เข้าสู่ระบบ
          <span class="auth-kind">
            <?php echo e($kinds[$tab]['icon'] . ' ' . $kinds[$tab]['label']); ?>
          </span>
        </h3>

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
              <div class="input-reveal">
                <input class="input" type="password" id="national_id" name="national_id"
                       inputmode="numeric" placeholder="เลขบัตรประชาชน 13 หลัก" required>
                <button type="button" class="reveal-btn" data-reveal-password="national_id"
                        aria-controls="national_id" aria-pressed="false"
                        aria-label="แสดงรหัสผ่าน" hidden>แสดง</button>
              </div>
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
              <div class="input-reveal">
                <input class="input" type="password" id="password" name="password"
                       placeholder="••••••••" required autocomplete="current-password">
                <button type="button" class="reveal-btn" data-reveal-password="password"
                        aria-controls="password" aria-pressed="false"
                        aria-label="แสดงรหัสผ่าน" hidden>แสดง</button>
              </div>
            </div>

            <div class="hint" style="margin-bottom:16px">
              ระบบจะพาไปยังหน้าจอตามบทบาทของบัญชีโดยอัตโนมัติ
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding:14px;border-radius:11px;font-size:15px">
              เข้าสู่ระบบ
            </button>
          </form>
        <?php endif; ?>

      <?php endif; ?>

      <?php if ($repo->registrationOpen()): ?>
        <p style="text-align:center;font-size:13px;color:var(--text-dim);margin-top:18px">
          ยังไม่มีบัญชีสถานศึกษา? <a href="<?php echo e(url('register')); ?>">สมัครใช้งาน</a>
        </p>
      <?php endif; ?>

    </div>
  </div>
</div>
