<?php
/**
 * Per-department report.
 *
 * @var array $departments
 * @var int $year
 */
$cols = 'grid-template-columns:1.6fr .8fr .8fr .8fr 1.2fr';
?>
<h1 class="page-title">รายงานตามแผนก</h1>
<p class="page-sub">อัตราการมีงานทำและศึกษาต่อของแต่ละสาขาวิชา · ปีสำรวจ <?php echo e($year); ?></p>

<div class="table">
  <div class="table-head" style="<?php echo $cols; ?>">
    <span>สาขาวิชา</span><span>ศิษย์เก่า</span><span>ตอบแล้ว</span><span>มีงาน/ศึกษาต่อ</span><span>อัตรา</span>
  </div>

  <?php if (!$departments): ?>
    <div class="table-empty">ยังไม่มีข้อมูลสาขาวิชา</div>
  <?php else: ?>
    <?php foreach ($departments as $dept): ?>
      <div class="table-row" style="<?php echo $cols; ?>">
        <span class="cell-title"><?php echo e($dept['name']); ?></span>
        <span class="cell-dim"><?php echo e(num($dept['total'])); ?></span>
        <span class="cell-dim"><?php echo e(num($dept['answered'])); ?></span>
        <span class="cell-dim"><?php echo e(num($dept['placed'])); ?></span>
        <span style="display:flex;align-items:center;gap:10px">
          <span class="bar-track" style="flex:1;min-width:60px">
            <span class="bar-fill" style="display:block;width:<?php echo e((int) $dept['pct']); ?>%"></span>
          </span>
          <b style="color:var(--primary);font-size:13px;min-width:42px;text-align:right"><?php echo e($dept['pct']); ?>%</b>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
