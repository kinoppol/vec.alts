<?php
/**
 * Transfer current students from RMS, one slice at a time.
 *
 * @var array $schools
 * @var int $selectedSchool
 * @var string $baseUrl
 * @var int $chunk
 * @var int $studentCount  students already held for the selected institution
 */
?>
<h1 class="page-title">โอนข้อมูลนักเรียนจากระบบ RMS</h1>
<p class="page-sub">
  ดึงรายชื่อผู้กำลังศึกษาจากระบบ RMS เข้ามาเป็นข้อมูลนักเรียนในระบบนี้
  เมื่อสำเร็จการศึกษาแล้วข้อมูลชุดเดิมจะกลายเป็นศิษย์เก่าโดยไม่ต้องกรอกใหม่
</p>

<div class="card" style="margin-bottom:20px">
  <form method="get" action="<?php echo e(url()); ?>"
        style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="r" value="centraladmin/import-students">
    <div style="flex:1;min-width:260px">
      <label class="label" for="school_pick">สถานศึกษาที่จะโอนข้อมูลเข้า</label>
      <select class="input" id="school_pick" name="school_id" data-auto-submit>
        <option value="0">— กรุณาเลือกสถานศึกษา —</option>
        <?php foreach ($schools as $school): ?>
          <option value="<?php echo e($school['id']); ?>"
                  <?php echo (int) $selectedSchool === (int) $school['id'] ? 'selected' : ''; ?>>
            <?php echo e($school['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="hint">แหล่งข้อมูลจะใช้ที่อยู่ RMS ของสถานศึกษาที่เลือก</div>
    </div>
    <button type="submit" class="btn">เปลี่ยน</button>
  </form>
</div>

<?php if ($selectedSchool < 1): ?>
  <div class="alert alert-info">เลือกสถานศึกษาด้านบนก่อน จึงจะเริ่มโอนข้อมูลได้</div>

<?php elseif (trim($baseUrl) === ''): ?>
  <div class="alert alert-warn">
    สถานศึกษานี้ยังไม่ได้กำหนดที่อยู่ระบบ RMS —
    กำหนดได้ที่ข้อมูลสถานศึกษา หรือตั้งค่าเริ่มต้นที่
    <a href="<?php echo e(url('centraladmin/settings')); ?>">เมนูตั้งค่าระบบ</a>
  </div>

<?php else: ?>

  <div class="card" style="margin-bottom:20px">
    <dl class="kv">
      <dt>แหล่งข้อมูล</dt>
      <dd style="word-break:break-all"><?php echo e($baseUrl); ?>/api_connection.php?app_name=nutty&amp;data=std2018_student</dd>
      <dt>ขนาดต่อรอบ</dt><dd><?php echo e($chunk); ?> รายการ</dd>
      <dt>นักเรียนในระบบขณะนี้</dt><dd><?php echo e(num($studentCount)); ?> คน</dd>
    </dl>
  </div>

  <div class="card card-lg" style="max-width:720px">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">เริ่มโอนข้อมูล</h3>
    <p class="hint" style="margin:0 0 18px">
      ระบบจะนับจำนวนทั้งหมดก่อน แล้วทยอยโอนทีละ <?php echo e($chunk); ?> รายการ
      จึงเห็นความคืบหน้าเป็นเปอร์เซ็นต์จริง
      กดซ้ำได้ ระบบจะปรับปรุงคนเดิมแทนการสร้างซ้ำ
      <b>ระหว่างโอนอย่าปิดหน้านี้</b> เพราะรอบการทำงานอยู่ที่เบราว์เซอร์
    </p>

    <div id="student-transfer"
         data-endpoint="<?php echo e(url('centraladmin/import-students')); ?>"
         data-school="<?php echo e($selectedSchool); ?>"
         data-row="<?php echo e($chunk); ?>"
         data-token="<?php echo e(csrf_token()); ?>">

      <button type="button" class="btn btn-primary btn-lg" data-start
              data-confirm="เริ่มโอนข้อมูลนักเรียนทั้งหมดจากระบบ RMS?">
        เริ่มโอนข้อมูลนักเรียน
      </button>

      <div data-panel hidden style="margin-top:20px">
        <div class="progress-track">
          <div class="progress-fill" data-bar style="width:0%"></div>
        </div>
        <div style="display:flex;justify-content:space-between;gap:12px;margin-top:10px;flex-wrap:wrap">
          <span class="cell-dim" data-status>กำลังเตรียมข้อมูล…</span>
          <span style="font-weight:700;color:var(--primary)" data-percent>0%</span>
        </div>

        <div class="grid-4" style="gap:12px;margin-top:18px">
          <div><div class="kpi-label">เพิ่มใหม่</div><div class="kpi-value" style="font-size:22px" data-added>0</div></div>
          <div><div class="kpi-label">ปรับปรุง</div><div class="kpi-value" style="font-size:22px" data-updated>0</div></div>
          <div><div class="kpi-label">ข้าม</div><div class="kpi-value" style="font-size:22px" data-skipped>0</div></div>
          <div><div class="kpi-label">อ่านแล้ว</div><div class="kpi-value" style="font-size:22px" data-done>0</div></div>
        </div>

        <div class="alert alert-warn" data-nologin-box hidden style="margin-top:14px">
          มี <b data-nologin>0</b> คนที่เลขบัตรประชาชนในระบบ RMS ไม่ครบ 13 หลัก
          ระบบบันทึกข้อมูลไว้แล้วแต่คนกลุ่มนี้จะยังเข้าสู่ระบบไม่ได้
          จนกว่าจะแก้เลขบัตรที่ต้นทางแล้วโอนใหม่
        </div>

        <div data-errors hidden style="margin-top:16px">
          <h4 style="font-size:14px;font-weight:700;margin-bottom:8px">รายการที่ผิดพลาด</h4>
          <div class="sql-log" data-error-list></div>
        </div>
      </div>

      <noscript>
        <div class="alert alert-warn" style="margin-top:16px">
          หน้านี้ต้องใช้ JavaScript เพราะข้อมูลมีหลายพันรายการ
          จึงต้องทยอยโอนทีละรอบจากฝั่งเบราว์เซอร์
        </div>
      </noscript>
    </div>
  </div>

  <div class="card card-lg" style="max-width:720px;margin-top:20px">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">กลุ่มเรียนและครูที่ปรึกษา</h3>
    <p class="hint" style="margin:0 0 16px">
      ดึงกลุ่มเรียน (<code>std2018_studentgroup</code>) แล้วผูกครูที่ปรึกษาให้แต่ละกลุ่ม
      โดยจับคู่จาก <b>เลขประจำตัวประชาชน</b> ของครู (<code>teacherIdcard</code>)
      กับบัญชีบุคลากรที่โอนมาแล้ว จากนั้นกำหนดครูที่ปรึกษาให้นักเรียนทุกคนตามกลุ่มเรียนของตน
    </p>

    <?php if ($groupSummary !== null && $groupSummary['groups'] > 0): ?>
      <dl class="kv" style="margin-bottom:16px">
        <dt>กลุ่มเรียนในระบบ</dt><dd><?php echo e(num($groupSummary['groups'])); ?> กลุ่ม</dd>
        <dt>กลุ่มที่มีครูที่ปรึกษา</dt><dd><?php echo e(num($groupSummary['with_advisor'])); ?> กลุ่ม</dd>
        <dt>นักเรียนที่มีครูที่ปรึกษา</dt><dd><?php echo e(num($groupSummary['students_linked'])); ?> คน</dd>
      </dl>
    <?php endif; ?>

    <div class="alert alert-info" style="margin-bottom:16px">
      ควรโอน <b>ข้อมูลผู้ใช้ (บุคลากร)</b> และ <b>ข้อมูลนักเรียน</b> ให้เรียบร้อยก่อน
      ครูที่ยังไม่มีบัญชีในระบบจะผูกไม่ได้ และนักเรียนที่ยังไม่ได้โอนก็จะยังไม่มีครูที่ปรึกษา
    </div>

    <div id="group-transfer"
         data-endpoint="<?php echo e(url('centraladmin/import-students')); ?>"
         data-school="<?php echo e($selectedSchool); ?>"
         data-token="<?php echo e(csrf_token()); ?>">

      <button type="button" class="btn btn-primary" data-group-start
              data-confirm="โอนข้อมูลกลุ่มเรียนและผูกครูที่ปรึกษา?">
        โอนกลุ่มเรียนและผูกครูที่ปรึกษา
      </button>

      <div data-group-panel hidden style="margin-top:18px">
        <div class="cell-dim" data-group-status>กำลังดำเนินการ…</div>

        <div class="grid-4" style="gap:12px;margin-top:14px">
          <div><div class="kpi-label">กลุ่มเพิ่มใหม่</div><div class="kpi-value" style="font-size:22px" data-g-added>0</div></div>
          <div><div class="kpi-label">กลุ่มปรับปรุง</div><div class="kpi-value" style="font-size:22px" data-g-updated>0</div></div>
          <div><div class="kpi-label">ผูกครูได้</div><div class="kpi-value" style="font-size:22px" data-g-linked>0</div></div>
          <div><div class="kpi-label">นักเรียนที่ผูกแล้ว</div><div class="kpi-value" style="font-size:22px" data-g-students>0</div></div>
        </div>

        <div class="alert alert-warn" data-g-warn hidden style="margin-top:14px">
          <div data-g-warn-text></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card" style="max-width:720px;margin-top:20px">
    <h3 style="font-size:15px;font-weight:700;margin-bottom:10px">ข้อมูลที่โอนเข้ามา</h3>
    <p class="cell-dim" style="line-height:1.9">
      นักเรียนจะถูกบันทึกในสถานะ <b>กำลังศึกษา</b> เข้าสู่ระบบด้วย
      <b>รหัสนักศึกษา</b> และ <b>เลขบัตรประชาชน</b> เช่นเดียวกับศิษย์เก่า
      สาขาวิชา (majorNameTh) จะถูกจับคู่กับสาขาที่มีอยู่ ถ้ายังไม่มีจะสร้างให้อัตโนมัติ
      ส่วนระดับชั้นแปลงจาก gradeNameTh เป็น ปวช./ปวส.
    </p>
    <p class="hint" style="margin-top:10px">
      ตรวจสอบรายชื่อที่โอนมาแล้วได้ที่เมนู
      <a href="<?php echo e(url('schooladmin/alumni')); ?>">ข้อมูลศิษย์เก่า</a>
      ของผู้ดูแลสถานศึกษา (มีตัวกรอง “กำลังศึกษา” และค้นหาแบ่งหน้า)
    </p>
  </div>

<?php endif; ?>
