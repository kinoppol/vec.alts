<?php
/**
 * Sign in, in two steps: pick the kind of account, then fill in the matching
 * form.
 *
 * The step exists because the two kinds take completely different
 * credentials. A single screen that opened on one of them had staff typing
 * their email address into the field asking for a student code.
 *
 * @var string $tab '' when nothing is chosen yet, otherwise 'alumni', 'first'
 *                  (first use or forgotten password) or 'staff'
 * @var array $old  previously submitted values
 * @var bool $codeRequired false during the switch-over, when someone without a
 *                  password may still sign in with their national ID
 */
$tab = isset($tab) ? $tab : '';
$old = isset($old) ? $old : array();
$codeRequired = !empty($codeRequired);
// The first-use form belongs to the same kind of account.
$kindKey = $tab === 'first' ? 'alumni' : $tab;

$kinds = array(
    // Students and graduates sign in with exactly the same two credentials;
    // the system reads which of the two they are from their record, so
    // splitting this into a third card would only ask a question that has no
    // effect on what happens next.
    'alumni' => array(
        'icon'  => '🎓',
        'label' => 'ผู้สำเร็จการศึกษา / กำลังศึกษา',
        'desc'  => 'ผู้สำเร็จการศึกษาและผู้ที่กำลังศึกษา · ใช้รหัสนักศึกษาและรหัสผ่าน',
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
      <h2>ยินดีต้อนรับกลับ<br>สู่<?php echo e($appName); ?></h2>
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

        <div class="kind-list">
          <?php foreach ($kinds as $key => $kind): ?>
            <a class="kind" href="<?php echo e(url('login', array('tab' => $key))); ?>">
              <span class="kind-icon" aria-hidden="true"><?php echo $kind['icon']; ?></span>
              <span>
                <span class="kind-title"><?php echo e($kind['label']); ?></span>
                <span class="kind-desc"><?php echo e($kind['desc']); ?></span>
              </span>
              <span class="kind-go" aria-hidden="true">→</span>
            </a>
          <?php endforeach; ?>
        </div>

      <?php else: ?>

        <a class="auth-change" href="<?php echo e(url('login')); ?>">← เปลี่ยนประเภทผู้ใช้งาน</a>

        <h3 class="auth-title">
          เข้าสู่ระบบ
          <span class="auth-kind">
            <?php echo e($kinds[$kindKey]['icon'] . ' ' . $kinds[$kindKey]['label']); ?>
          </span>
        </h3>

        <?php echo $this->partial('layout/flash'); ?>

        <?php if ($tab === 'alumni'): ?>
          <form method="post" action="<?php echo e(url('login')); ?>" autocomplete="on">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="tab" value="alumni">

            <div class="field">
              <label class="label" for="student_code">รหัสนักศึกษา</label>
              <input class="input" type="text" id="student_code" name="student_code"
                     inputmode="numeric" placeholder="เช่น 6231010001" required autocomplete="username"
                     value="<?php echo e(arr($old, 'student_code', '')); ?>">
            </div>

            <div class="field">
              <label class="label" for="password">รหัสผ่าน</label>
              <div class="input-reveal">
                <input class="input" type="password" id="password" name="password"
                       placeholder="รหัสผ่านที่คุณตั้งไว้" required autocomplete="current-password">
                <button type="button" class="reveal-btn" data-reveal-password="password"
                        aria-controls="password" aria-pressed="false"
                        aria-label="แสดงรหัสผ่าน" hidden>แสดง</button>
              </div>
              <?php if (!$codeRequired): ?>
                <div class="hint">
                  ช่วงเปลี่ยนผ่าน: หากยังไม่เคยตั้งรหัสผ่าน ใช้เลขบัตรประชาชนแทนได้ชั่วคราว
                </div>
              <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding:14px;border-radius:11px;font-size:15px">
              เข้าสู่ระบบ
            </button>
          </form>

          <p style="text-align:center;font-size:14px;margin-top:16px">
            ยังไม่มีรหัสผ่าน หรือลืมรหัสผ่าน?
            <a href="<?php echo e(url('login', array('tab' => 'first'))); ?>">เข้าใช้ครั้งแรก / ลืมรหัสผ่าน</a>
          </p>

        <?php elseif ($tab === 'first'): ?>
          <p class="auth-lead">
            ใช้รหัสเข้าใช้ครั้งแรกที่ได้รับจากครูที่ปรึกษา แล้วระบบจะให้ตั้งรหัสผ่านของคุณเอง
          </p>
          <form method="post" action="<?php echo e(url('login')); ?>" autocomplete="off">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="tab" value="first">

            <div class="field">
              <label class="label" for="student_code">รหัสนักศึกษา</label>
              <input class="input" type="text" id="student_code" name="student_code"
                     inputmode="numeric" placeholder="เช่น 6231010001" required
                     value="<?php echo e(arr($old, 'student_code', '')); ?>">
            </div>

            <div class="field">
              <label class="label" for="national_id">เลขบัตรประชาชน</label>
              <div class="input-reveal">
                <input class="input" type="password" id="national_id" name="national_id"
                       inputmode="numeric" placeholder="เลขบัตรประชาชน 13 หลัก" required>
                <button type="button" class="reveal-btn" data-reveal-password="national_id"
                        aria-controls="national_id" aria-pressed="false"
                        aria-label="แสดงเลขบัตรประชาชน" hidden>แสดง</button>
              </div>
            </div>

            <div class="field">
              <label class="label" for="access_code">รหัสเข้าใช้ครั้งแรก (จากครูที่ปรึกษา)</label>
              <input class="input" type="text" id="access_code" name="access_code"
                     placeholder="เช่น ABCD-2345" required autocapitalize="characters"
                     style="font-family:ui-monospace,Consolas,monospace;letter-spacing:2px">
              <div class="hint">รหัส 8 ตัวอักษร ใช้ได้ครั้งเดียว · ถ้าหมดอายุหรือทำหาย ขอรหัสใหม่จากครูที่ปรึกษา</div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding:14px;border-radius:11px;font-size:15px">
              ยืนยันตัวตนและตั้งรหัสผ่าน
            </button>
          </form>

          <p style="text-align:center;font-size:14px;margin-top:16px">
            มีรหัสผ่านแล้ว? <a href="<?php echo e(url('login', array('tab' => 'alumni'))); ?>">เข้าสู่ระบบด้วยรหัสผ่าน</a>
          </p>

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
