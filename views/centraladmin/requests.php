<?php
/**
 * Pending institution sign-up requests.
 *
 * @var array $requests
 */
?>
<h1 class="page-title">คำขอสมัครใช้งาน</h1>
<p class="page-sub">ตรวจสอบข้อมูลสถานศึกษาก่อนเปิดใช้งาน เมื่ออนุมัติแล้วบัญชีผู้ดูแลสถานศึกษาจะเข้าใช้งานได้ทันที</p>

<?php if (!$requests): ?>
  <div class="card"><p class="cell-dim">ไม่มีคำขอที่รอการอนุมัติ</p></div>
<?php else: ?>
  <?php foreach ($requests as $request): ?>
    <div class="card" style="margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div>
          <div style="font-size:17px;font-weight:700;color:var(--text)"><?php echo e($request['name']); ?></div>
          <div class="cell-dim" style="margin-top:6px">
            <?php echo e($request['province'] !== '' ? $request['province'] : 'ไม่ระบุจังหวัด'); ?>
            <?php if ($request['affiliation'] !== ''): ?>
              · สังกัด <?php echo e($request['affiliation']); ?>
            <?php endif; ?>
          </div>
          <dl class="kv" style="margin-top:14px">
            <dt>ผู้ประสานงาน</dt><dd><?php echo e($request['contact_name']); ?></dd>
            <dt>อีเมล</dt><dd><?php echo e($request['contact_email']); ?></dd>
            <dt>โทรศัพท์</dt><dd><?php echo e($request['contact_phone'] !== '' ? $request['contact_phone'] : '—'); ?></dd>
            <dt>ส่งคำขอเมื่อ</dt><dd><?php echo e(thai_date($request['created_at'])); ?></dd>
          </dl>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;min-width:160px">
          <form method="post" action="<?php echo e(url('centraladmin/school-status')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo e($request['id']); ?>">
            <button type="submit" name="status" value="active" class="btn btn-primary btn-block">
              อนุมัติและเปิดใช้งาน
            </button>
          </form>
          <form method="post" action="<?php echo e(url('centraladmin/school-status')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo e($request['id']); ?>">
            <button type="submit" name="status" value="suspended" class="btn btn-block btn-danger"
                    data-confirm="ปฏิเสธคำขอนี้?">ปฏิเสธคำขอ</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
