<?php
/**
 * Department (สาขาวิชา) management.
 *
 * @var array $departments  each row carries alumni_count
 */
$cols = 'grid-template-columns:2fr 1fr 1fr';
?>
<h1 class="page-title">จัดการสาขา</h1>
<p class="page-sub">สาขาวิชาที่เปิดสอน ใช้จัดกลุ่มศิษย์เก่าและแยกรายงานตามแผนก</p>

<div class="card" style="max-width:720px;margin-bottom:22px">
  <form method="post" action="<?php echo e(url('schooladmin/departments')); ?>"
        style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <?php echo csrf_field(); ?>
    <div style="flex:1;min-width:200px">
      <label class="label" for="name">ชื่อสาขาวิชา</label>
      <input class="input" type="text" id="name" name="name" required placeholder="เช่น ช่างยนต์">
    </div>
    <div style="width:140px">
      <label class="label" for="code">รหัสสาขา</label>
      <input class="input" type="text" id="code" name="code" placeholder="ไม่บังคับ">
    </div>
    <button type="submit" class="btn btn-primary">เพิ่มสาขา</button>
  </form>
</div>

<div class="table" style="max-width:720px">
  <div class="table-head" style="<?php echo $cols; ?>">
    <span>สาขาวิชา</span><span>รหัส</span><span>ศิษย์เก่า</span>
  </div>
  <?php if (!$departments): ?>
    <div class="table-empty">ยังไม่มีสาขาวิชา — เพิ่มรายการแรกได้จากฟอร์มด้านบน</div>
  <?php else: ?>
    <?php foreach ($departments as $dept): ?>
      <div class="table-row" style="<?php echo $cols; ?>">
        <span class="cell-title"><?php echo e($dept['name']); ?></span>
        <span class="cell-dim"><?php echo e($dept['code'] !== '' ? $dept['code'] : '—'); ?></span>
        <span class="cell-dim"><?php echo e(num($dept['alumni_count'])); ?> คน</span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
