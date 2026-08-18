<?php
/**
 * Central administrator overview: every institution in the system.
 *
 * @var array $summary
 * @var array $schools
 */
$cols = 'grid-template-columns:1.6fr 1fr 1fr 1fr';
?>
<h1 class="page-title">ผู้ดูแลระบบกลาง</h1>
<p class="page-sub">บริหารสถานศึกษาที่ใช้งานทั้งหมดในระบบ · ปีสำรวจ <?php echo e($summary['survey_year']); ?></p>

<div class="grid-4" style="margin-bottom:22px">
  <div class="card card-sm">
    <div class="kpi-label">สถานศึกษาทั้งหมด</div>
    <div class="kpi-value" style="font-size:28px"><?php echo e(num($summary['schools'])); ?></div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">ศิษย์เก่าในระบบ</div>
    <div class="kpi-value" style="font-size:28px"><?php echo e(num($summary['alumni'])); ?></div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">ตอบแบบสำรวจปีนี้</div>
    <div class="kpi-value" style="font-size:28px"><?php echo e(num($summary['answered'])); ?></div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">คำขอรออนุมัติ</div>
    <div class="kpi-value" style="font-size:28px"><?php echo e(num($summary['pending_schools'])); ?></div>
  </div>
</div>

<div class="table">
  <div class="table-toolbar">
    <h3 style="font-size:15px;font-weight:700;color:var(--text)">สถานศึกษาในระบบ</h3>
    <?php if ($summary['pending_schools'] > 0): ?>
      <a href="<?php echo e(url('centraladmin/requests')); ?>" style="font-size:13px">
        คำขอสมัครใหม่ <?php echo e($summary['pending_schools']); ?> รายการ →
      </a>
    <?php else: ?>
      <span class="cell-dim">ไม่มีคำขอค้างอยู่</span>
    <?php endif; ?>
  </div>

  <div class="table-head" style="<?php echo $cols; ?>">
    <span>สถานศึกษา</span><span>จังหวัด</span><span>ศิษย์เก่า</span><span>สถานะ</span>
  </div>

  <?php if (!$schools): ?>
    <div class="table-empty">ยังไม่มีสถานศึกษาในระบบ</div>
  <?php else: ?>
    <?php foreach ($schools as $school): ?>
      <?php
      if ($school['status'] === 'active') {
          $badge = array('done', 'ใช้งาน');
      } elseif ($school['status'] === 'pending') {
          $badge = array('warn', 'รออนุมัติ');
      } else {
          $badge = array('wait', 'ระงับ');
      }
      ?>
      <div class="table-row" style="<?php echo $cols; ?>">
        <div>
          <div class="cell-title"><?php echo e($school['name']); ?></div>
          <?php if ($school['affiliation'] !== ''): ?>
            <div class="cell-sub">สังกัด <?php echo e($school['affiliation']); ?></div>
          <?php endif; ?>
        </div>
        <span class="cell-dim"><?php echo e($school['province'] !== '' ? $school['province'] : '—'); ?></span>
        <span class="cell-dim">
          <?php echo e((int) $school['alumni_count'] > 0 ? num($school['alumni_count']) : '—'); ?>
        </span>
        <span style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span class="badge badge-<?php echo e($badge[0]); ?>"><?php echo e($badge[1]); ?></span>
          <form method="post" action="<?php echo e(url('centraladmin/school-status')); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo e($school['id']); ?>">
            <?php if ($school['status'] === 'active'): ?>
              <button type="submit" name="status" value="suspended" class="btn btn-sm"
                      data-confirm="ระงับการใช้งานสถานศึกษานี้?">ระงับ</button>
            <?php else: ?>
              <button type="submit" name="status" value="active" class="btn btn-sm"
                      style="color:var(--primary)">เปิดใช้งาน</button>
            <?php endif; ?>
          </form>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
