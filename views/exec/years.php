<?php
/**
 * Year-on-year comparison.
 *
 * @var array $years
 */
?>
<h1 class="page-title">เปรียบเทียบปีการศึกษา</h1>
<p class="page-sub">อัตราการมีงานทำและศึกษาต่อ แยกตามปีที่สำเร็จการศึกษา</p>

<?php if (!$years): ?>
  <div class="card"><p class="cell-dim">ยังไม่มีข้อมูลเพียงพอสำหรับการเปรียบเทียบ</p></div>
<?php else: ?>

  <div class="card" style="border-radius:16px;padding:28px;margin-bottom:20px">
    <div style="display:flex;align-items:flex-end;gap:18px;height:220px;padding:0 4px">
      <?php foreach ($years as $row): ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;height:100%;justify-content:flex-end">
          <span style="font-size:13px;font-weight:700;color:var(--primary)"><?php echo e($row['pct']); ?>%</span>
          <div style="width:100%;height:<?php echo e(max(2, (int) $row['pct'])); ?>%;border-radius:8px 8px 0 0;background:linear-gradient(180deg,var(--primary),var(--primary-2))"
               title="<?php echo e($row['placed'] . ' จาก ' . $row['answered'] . ' คน'); ?>"></div>
          <span style="font-size:12px;color:var(--text-dim)"><?php echo e($row['year']); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="table">
    <div class="table-head" style="grid-template-columns:1fr 1fr 1fr 1fr 1fr">
      <span>ปีการศึกษา</span><span>ศิษย์เก่า</span><span>ตอบแล้ว</span><span>มีงาน/ศึกษาต่อ</span><span>อัตรา</span>
    </div>
    <?php foreach (array_reverse($years) as $row): ?>
      <div class="table-row" style="grid-template-columns:1fr 1fr 1fr 1fr 1fr">
        <span class="cell-title"><?php echo e($row['year']); ?></span>
        <span class="cell-dim"><?php echo e(num($row['total'])); ?></span>
        <span class="cell-dim"><?php echo e(num($row['answered'])); ?></span>
        <span class="cell-dim"><?php echo e(num($row['placed'])); ?></span>
        <span><b style="color:var(--primary)"><?php echo e($row['pct']); ?>%</b></span>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>
