<?php
/**
 * Migration management for the central administrator.
 *
 * @var array $rows       one entry per migration, applied or not
 * @var array $env        server facts
 * @var int $pendingCount
 * @var array|null $lastResult  outcome of the run that just happened
 */
$cols = 'grid-template-columns:.7fr 2fr 1fr .9fr .8fr';
$lastResult = isset($lastResult) ? $lastResult : null;
?>
<h1 class="page-title">Migration ฐานข้อมูล</h1>
<p class="page-sub">
  ปรับโครงสร้างฐานข้อมูลให้ตรงกับเวอร์ชันของโปรแกรม ใช้ได้ทั้ง MySQL 5 และ MariaDB 10
</p>

<?php echo $this->partial('layout/flash'); ?>

<div class="grid-2" style="margin-bottom:22px">
  <div class="card card-sm">
    <div class="kpi-label">สถานะโครงสร้าง</div>
    <?php if ($pendingCount > 0): ?>
      <div class="kpi-value" style="font-size:24px;color:var(--warn)">
        รอปรับปรุง <?php echo e($pendingCount); ?> รายการ
      </div>
      <div class="kpi-sub" style="color:var(--text-dim)">กดปุ่ม “ปรับปรุงฐานข้อมูล” เพื่อดำเนินการ</div>
    <?php else: ?>
      <div class="kpi-value" style="font-size:24px;color:var(--ok)">เป็นปัจจุบันแล้ว</div>
      <div class="kpi-sub" style="color:var(--text-dim)">ไม่มี migration ที่ค้างอยู่</div>
    <?php endif; ?>
  </div>

  <div class="card card-sm">
    <dl class="kv">
      <dt>ฐานข้อมูล</dt><dd><?php echo e($env['db_flavour'] . ' ' . $env['db_version']); ?></dd>
      <dt>PHP</dt><dd><?php echo e($env['php']); ?></dd>
      <dt>ชุดอักขระ</dt><dd><?php echo e($env['charset']); ?></dd>
      <dt>Batch ล่าสุด</dt><dd><?php echo e($env['batch']); ?></dd>
    </dl>
  </div>
</div>

<div style="display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap">
  <form method="post" action="<?php echo e(url('admin/migrations')); ?>">
    <?php echo csrf_field(); ?>
    <button type="submit" name="action" value="migrate" class="btn btn-primary"
            <?php echo $pendingCount > 0 ? '' : 'disabled'; ?>>
      ปรับปรุงฐานข้อมูล (รัน <?php echo e($pendingCount); ?> รายการ)
    </button>
  </form>

  <form method="post" action="<?php echo e(url('admin/migrations')); ?>">
    <?php echo csrf_field(); ?>
    <button type="submit" name="action" value="rollback" class="btn btn-danger"
            data-confirm="ย้อนกลับ migration ชุดล่าสุด? การกระทำนี้อาจลบตารางหรือคอลัมน์ที่สร้างไว้ และข้อมูลในนั้นจะหายไป">
      ย้อนกลับชุดล่าสุด (batch <?php echo e($env['batch']); ?>)
    </button>
  </form>

  <a class="btn" href="<?php echo e(url('admin/migrations')); ?>">รีเฟรชสถานะ</a>
</div>

<?php if ($lastResult !== null): ?>
  <div class="card" style="margin-bottom:22px">
    <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">ผลการทำงานล่าสุด</h3>
    <?php if ($lastResult['sql']): ?>
      <div class="sql-log"><?php foreach ($lastResult['sql'] as $statement) {
          echo e($statement) . ";\n";
      } ?></div>
    <?php else: ?>
      <p class="cell-dim">ไม่มีคำสั่ง SQL ที่ถูกเรียกใช้</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="table">
  <div class="table-head" style="<?php echo $cols; ?>">
    <span>เวอร์ชัน</span><span>รายละเอียด</span><span>เมื่อ</span><span>Batch</span><span>สถานะ</span>
  </div>

  <?php foreach ($rows as $row): ?>
    <?php
    if ($row['state'] === 'applied') {
        $badge = array('done', 'ใช้งานแล้ว');
    } elseif ($row['state'] === 'pending') {
        $badge = array('warn', 'รอปรับปรุง');
    } else {
        $badge = array('danger', 'ไม่พบไฟล์');
    }
    ?>
    <div class="table-row" style="<?php echo $cols; ?>">
      <span class="cell-title"><?php echo e($row['version']); ?></span>
      <div>
        <div class="cell-title" style="font-weight:500"><?php echo e($row['name']); ?></div>
        <div class="cell-sub"><?php echo e($row['file']); ?></div>
      </div>
      <span class="cell-dim" style="font-size:12px">
        <?php echo e($row['applied_at'] !== null ? thai_date($row['applied_at']) : '—'); ?>
        <?php if ($row['runtime_ms'] !== null && $row['runtime_ms'] > 0): ?>
          <br><span style="opacity:.7"><?php echo e($row['runtime_ms']); ?> ms</span>
        <?php endif; ?>
      </span>
      <span class="cell-dim"><?php echo e($row['batch'] !== null ? $row['batch'] : '—'); ?></span>
      <span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span class="badge badge-<?php echo e($badge[0]); ?>"><?php echo e($badge[1]); ?></span>
        <?php if ($row['state'] === 'pending'): ?>
          <form method="post" action="<?php echo e(url('admin/migrations')); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="version" value="<?php echo e($row['version']); ?>">
            <button type="submit" name="action" value="migrate-one" class="btn btn-sm"
                    style="color:var(--primary)">รันรายการนี้</button>
          </form>
        <?php endif; ?>
      </span>
    </div>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
    <div class="table-empty">ไม่พบไฟล์ migration ในโฟลเดอร์ migrations/</div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:22px">
  <h3 style="font-size:15px;font-weight:700;margin-bottom:10px">การเพิ่ม migration ใหม่</h3>
  <p class="cell-dim" style="line-height:1.8">
    สร้างไฟล์ในโฟลเดอร์ <code>migrations/</code> ตั้งชื่อเป็น <code>NNNN_คำอธิบาย.php</code>
    โดยเรียงลำดับตามเลขนำหน้า ไฟล์ต้อง <code>return</code> อาร์เรย์ที่มีคีย์
    <code>name</code>, <code>up</code> และ <code>down</code>
    ฟังก์ชันจะได้รับอ็อบเจ็กต์ <code>Schema</code> ซึ่งมีเมท็อดช่วยเขียน DDL
    ที่ทำงานได้ทั้ง MySQL 5 และ MariaDB 10 เช่น
    <code>createTable()</code>, <code>addColumn()</code>, <code>addIndex()</code>
    ซึ่งตรวจสอบก่อนว่ามีอยู่แล้วหรือไม่ จึงเรียกซ้ำได้อย่างปลอดภัย
  </p>
</div>
