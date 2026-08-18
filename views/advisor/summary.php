<?php
/**
 * Status mix for the alumni one advisor looks after.
 *
 * @var array $summary
 * @var int $year
 */
$statuses = employment_statuses();
$max = 0;
foreach ($summary['by_status'] as $count) {
    if ($count > $max) {
        $max = $count;
    }
}
?>
<h1 class="page-title">สรุปกลุ่ม</h1>
<p class="page-sub">สัดส่วนสถานะของศิษย์เก่าในความดูแล · ปีสำรวจ <?php echo e($year); ?></p>

<div class="grid-4" style="margin-bottom:20px">
  <div class="card card-sm">
    <div class="kpi-label">ศิษย์เก่าทั้งหมด</div>
    <div class="kpi-value"><?php echo e(num($summary['total'])); ?></div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">อัปเดตแล้ว</div>
    <div class="kpi-value"><?php echo e(num($summary['updated'])); ?></div>
    <div class="kpi-sub"><?php echo e(pct($summary['updated'], $summary['total'])); ?>% ของทั้งหมด</div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">มีงานทำ</div>
    <div class="kpi-value"><?php echo e(num($summary['employed'])); ?></div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">ศึกษาต่อ</div>
    <div class="kpi-value"><?php echo e(num($summary['study'])); ?></div>
  </div>
</div>

<div class="card" style="border-radius:16px;padding:24px">
  <h3 style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:20px">จำแนกตามสถานะ</h3>
  <?php foreach ($statuses as $code => $info): ?>
    <?php $count = isset($summary['by_status'][$code]) ? $summary['by_status'][$code] : 0; ?>
    <div class="bar-row">
      <div class="bar-head">
        <span style="color:var(--text)"><?php echo e($info['icon'] . ' ' . $info['label']); ?></span>
        <span style="color:var(--text-dim)"><?php echo e(num($count)); ?> คน</span>
      </div>
      <div class="bar-track">
        <div class="bar-fill" style="width:<?php echo e($max > 0 ? round(($count / $max) * 100) : 0); ?>%"></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
