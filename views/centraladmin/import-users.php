<?php
/**
 * Transfer staff accounts in from the configured RMS installation.
 *
 * @var string $baseUrl
 * @var string $feedUrl
 * @var string $apiPath
 * @var array $schools
 * @var array|null $summary  outcome of a transfer that just ran
 * @var array|null $preview  outcome of a dry run
 * @var string $lastImport
 * @var int $pendingAvatars
 */
$roles = staff_roles();
unset($roles['centraladmin']);
?>
<h1 class="page-title">โอนข้อมูลผู้ใช้จากระบบ RMS</h1>
<p class="page-sub">
  ดึงรายชื่อบุคลากรจากระบบ RMS เข้ามาเป็นบัญชีผู้ใช้ในระบบนี้
  โอนซ้ำได้ ระบบจะปรับปรุงคนเดิมแทนการสร้างซ้ำ
</p>

<?php if (trim($baseUrl) === ''): ?>
  <div class="alert alert-warn">
    ยังไม่ได้กำหนดที่อยู่ระบบ RMS —
    <a href="<?php echo e(url('centraladmin/settings')); ?>">ไปที่เมนูตั้งค่าระบบ</a>
    เพื่อกรอกที่อยู่ก่อน
  </div>
<?php else: ?>

  <div class="card" style="margin-bottom:20px">
    <dl class="kv">
      <dt>แหล่งข้อมูล</dt><dd style="word-break:break-all"><?php echo e($feedUrl); ?></dd>
      <dt>ที่อยู่หลัก</dt><dd><?php echo e($baseUrl); ?> <span style="font-weight:400;color:var(--text-dim)">(แก้ไขได้ที่เมนูตั้งค่าระบบ)</span></dd>
      <dt>พาธ API</dt><dd style="font-weight:400"><code><?php echo e($apiPath); ?></code> <span style="color:var(--text-dim)">(กำหนดไว้ในโปรแกรม)</span></dd>
      <dt>โอนครั้งล่าสุด</dt><dd><?php echo e($lastImport !== '' ? thai_date($lastImport) : 'ยังไม่เคยโอน'); ?></dd>
    </dl>
  </div>

  <?php if ($summary !== null): ?>
    <div class="card" style="margin-bottom:20px">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:14px">ผลการโอนข้อมูล</h3>
      <div class="grid-4" style="gap:12px;margin-bottom:16px">
        <div><div class="kpi-label">เพิ่มใหม่</div><div class="kpi-value" style="font-size:24px"><?php echo e(num($summary['created'])); ?></div></div>
        <div><div class="kpi-label">ปรับปรุง</div><div class="kpi-value" style="font-size:24px"><?php echo e(num($summary['updated'])); ?></div></div>
        <div><div class="kpi-label">ผิดพลาด</div><div class="kpi-value" style="font-size:24px"><?php echo e(num($summary['failed'])); ?></div></div>
        <div><div class="kpi-label">ดาวน์โหลดรูป</div><div class="kpi-value" style="font-size:24px"><?php echo e(num($summary['avatar_saved'])); ?></div></div>
      </div>

      <?php if ($summary['no_password'] > 0): ?>
        <div class="alert alert-warn">
          มี <?php echo e($summary['no_password']); ?> คนที่ระบบ RMS ไม่มีรหัสผ่าน (<code>ath_pass</code> ว่าง)
          ระบบตั้งรหัสผ่านสุ่มที่เดาไม่ได้ให้ไว้ก่อน บุคคลเหล่านี้จะยังเข้าสู่ระบบไม่ได้
          จนกว่าผู้ดูแลจะตั้งรหัสผ่านใหม่ให้
        </div>
      <?php endif; ?>

      <?php if ($summary['no_email'] > 0): ?>
        <div class="alert alert-info">
          มี <?php echo e($summary['no_email']); ?> คนที่ไม่มีอีเมลในระบบ RMS
          บุคคลเหล่านี้เข้าสู่ระบบด้วย <b>ชื่อผู้ใช้</b> (รหัสบุคลากร) แทนอีเมลได้
        </div>
      <?php endif; ?>

      <?php if ($summary['avatar_pending'] > 0 || $summary['avatar_failed'] > 0): ?>
        <div class="alert alert-warn">
          รูปโปรไฟล์: ดาวน์โหลดไม่สำเร็จ <?php echo e($summary['avatar_failed']); ?> รูป ·
          ยังไม่ได้ดาวน์โหลด <?php echo e($summary['avatar_pending']); ?> รูป (หมดเวลาที่กำหนดไว้ต่อรอบ)
          กดปุ่ม “ดาวน์โหลดรูปที่เหลือ” ด้านล่างเพื่อทำต่อ
        </div>
      <?php endif; ?>

      <?php if ($summary['errors']): ?>
        <h4 style="font-size:14px;font-weight:700;margin:14px 0 8px">รายการที่ผิดพลาด</h4>
        <div class="sql-log"><?php foreach ($summary['errors'] as $line) {
            echo e($line) . "\n";
        } ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($preview !== null): ?>
    <div class="card" style="margin-bottom:20px">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">ผลการตรวจสอบข้อมูล</h3>
      <dl class="kv" style="margin-bottom:16px">
        <dt>รายการทั้งหมดในแหล่งข้อมูล</dt><dd><?php echo e(num($preview['total'])); ?></dd>
        <dt>จะโอน (people_exit = 0)</dt><dd><?php echo e(num($preview['eligible'])); ?></dd>
        <dt>ข้าม (ลาออก/พ้นสภาพ)</dt><dd><?php echo e(num($preview['skipped_exit'])); ?></dd>
      </dl>

      <h4 style="font-size:14px;font-weight:700;margin-bottom:8px">ตัวอย่าง 8 รายการแรก</h4>
      <div class="table" style="border-radius:12px">
        <div class="table-head" style="grid-template-columns:1fr 1.6fr 1.6fr .8fr">
          <span>ชื่อผู้ใช้</span><span>ชื่อ-นามสกุล</span><span>อีเมล</span><span>รูป</span>
        </div>
        <?php foreach ($preview['sample'] as $row): ?>
          <div class="table-row" style="grid-template-columns:1fr 1.6fr 1.6fr .8fr">
            <span class="cell-dim"><?php echo e(arr($row, 'people_id', '')); ?></span>
            <span class="cell-title" style="font-weight:500">
              <?php echo e(trim(arr($row, 'people_name', '') . ' ' . arr($row, 'people_surname', ''))); ?>
            </span>
            <span class="cell-dim"><?php echo e(trim(arr($row, 'people_email', '')) !== '' ? arr($row, 'people_email') : '— ไม่มี —'); ?></span>
            <span class="cell-dim"><?php echo trim(arr($row, 'people_pic', '')) !== '' ? 'มี' : 'ใช้ชื่อย่อ'; ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card card-lg" style="max-width:720px">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:18px">ตั้งค่าการโอนข้อมูล</h3>

    <form method="post" action="<?php echo e(url('centraladmin/import-users')); ?>">
      <?php echo csrf_field(); ?>

      <div class="grid-2">
        <div class="field">
          <label class="label" for="school_id">สังกัดสถานศึกษา</label>
          <select class="input" id="school_id" name="school_id">
            <option value="0">— ไม่สังกัดสถานศึกษา —</option>
            <?php foreach ($schools as $school): ?>
              <option value="<?php echo e($school['id']); ?>"><?php echo e($school['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">ใช้กับผู้ใช้ที่เพิ่มใหม่เท่านั้น</div>
        </div>

        <div class="field">
          <label class="label" for="role">บทบาทเริ่มต้น</label>
          <select class="input" id="role" name="role">
            <?php foreach ($roles as $code => $label): ?>
              <option value="<?php echo e($code); ?>" <?php echo $code === 'advisor' ? 'selected' : ''; ?>>
                <?php echo e($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint">ใช้กับผู้ใช้ที่เพิ่มใหม่เท่านั้น</div>
        </div>
      </div>

      <div class="field">
        <label class="label">
          <input type="checkbox" name="avatars" value="1" checked>
          ดาวน์โหลดรูปโปรไฟล์จากระบบ RMS
        </label>
        <div class="hint">
          ดึงจาก <code><?php echo e($baseUrl); ?>/files/</code> ตามด้วยค่า <code>people_pic</code>
          ผู้ที่ไม่มีรูปจะแสดงเป็นชื่อย่อแทน
        </div>
      </div>

      <div class="field">
        <label class="label">
          <input type="checkbox" name="update_passwords" value="1">
          ตั้งรหัสผ่านของผู้ใช้เดิมใหม่ตามค่าใน RMS
        </label>
        <div class="hint">
          ปกติไม่เลือก เพราะผู้ใช้ที่เคยเปลี่ยนรหัสผ่านในระบบนี้แล้วจะถูกเขียนทับ
          ส่วนผู้ใช้ที่เพิ่มใหม่จะได้รหัสผ่านจาก RMS เสมอ
        </div>
      </div>

      <div class="form-actions" style="justify-content:flex-start">
        <button type="submit" name="action" value="preview" class="btn">ตรวจสอบข้อมูลก่อน</button>
        <button type="submit" name="action" value="import" class="btn btn-primary"
                data-confirm="เริ่มโอนข้อมูลผู้ใช้จากระบบ RMS?">เริ่มโอนข้อมูล</button>
      </div>
    </form>

    <?php if ($pendingAvatars > 0): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:22px 0">
      <p class="hint" style="margin-bottom:10px">
        ยังมีผู้ใช้ <?php echo e($pendingAvatars); ?> คนที่ยังไม่มีรูปโปรไฟล์ในระบบ
      </p>
      <form method="post" action="<?php echo e(url('centraladmin/import-users')); ?>">
        <?php echo csrf_field(); ?>
        <button type="submit" name="action" value="avatars" class="btn">ดาวน์โหลดรูปที่เหลือ</button>
      </form>
    <?php endif; ?>
  </div>

<?php endif; ?>
