<?php
/**
 * Full alumni roster for one institution, with search and pagination.
 *
 * @var array $rows
 * @var array $filters
 * @var int $total
 * @var int $page
 * @var int $perPage
 * @var array $departments
 * @var array $advisors
 */
$cols = 'grid-template-columns:1.5fr 1fr .8fr 1fr 1fr';
$pages = (int) ceil($total / max(1, $perPage));
?>
<h1 class="page-title">ข้อมูลศิษย์เก่า</h1>
<p class="page-sub">รายชื่อผู้สำเร็จการศึกษาทั้งหมดของสถานศึกษา · ปีสำรวจ <?php echo e($filters['survey_year']); ?></p>

<div class="table">
  <form class="table-toolbar" method="get" action="<?php echo e(url()); ?>">
    <input type="hidden" name="r" value="schooladmin/alumni">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input class="input input-sm" type="search" name="q" placeholder="ค้นหาชื่อหรือรหัส"
             style="width:200px" value="<?php echo e($filters['search']); ?>">
      <select class="input input-sm" name="dept" data-auto-submit style="width:160px">
        <option value="">ทุกสาขา</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?php echo e($dept['id']); ?>"
                  <?php echo (int) $filters['department_id'] === (int) $dept['id'] ? 'selected' : ''; ?>>
            <?php echo e($dept['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select class="input input-sm" name="state" data-auto-submit style="width:140px">
        <option value="">ทุกสถานะ</option>
        <option value="pending" <?php echo $filters['state'] === 'pending' ? 'selected' : ''; ?>>รอติดตาม</option>
        <option value="updated" <?php echo $filters['state'] === 'updated' ? 'selected' : ''; ?>>อัปเดตแล้ว</option>
      </select>
      <button type="submit" class="btn btn-sm">ค้นหา</button>
    </div>
    <span class="cell-dim">พบ <?php echo e(num($total)); ?> รายการ</span>
  </form>

  <div class="table-head" style="<?php echo $cols; ?>">
    <span>ชื่อ - รหัส</span><span>สาขา</span><span>ปีจบ</span><span>ครูที่ปรึกษา</span><span>สถานะ</span>
  </div>

  <?php if (!$rows): ?>
    <div class="table-empty">
      ยังไม่มีข้อมูลศิษย์เก่า —
      <a href="<?php echo e(url('schooladmin/import')); ?>">นำเข้าจากไฟล์ CSV</a>
    </div>
  <?php else: ?>
    <?php foreach ($rows as $row): ?>
      <?php
      if ($row['employment_status'] === null) {
          $badge = array('wait', 'รอติดตาม');
      } elseif ((int) $row['is_draft'] === 1) {
          $badge = array('warn', 'บันทึกร่าง');
      } else {
          $badge = array('done', employment_label($row['employment_status']));
      }
      ?>
      <div class="table-row" style="<?php echo $cols; ?>">
        <div>
          <div class="cell-title"><?php echo e(trim($row['title'] . $row['first_name'] . ' ' . $row['last_name'])); ?></div>
          <div class="cell-sub">รหัส <?php echo e($row['student_code']); ?></div>
        </div>
        <span class="cell-dim"><?php echo e($row['department_name'] !== null ? $row['department_name'] : '—'); ?></span>
        <span class="cell-dim"><?php echo e((int) $row['graduation_year'] > 0 ? $row['graduation_year'] : '—'); ?></span>
        <span class="cell-dim"><?php echo e($row['advisor_name'] !== null ? $row['advisor_name'] : '—'); ?></span>
        <span><span class="badge badge-<?php echo e($badge[0]); ?>"><?php echo e($badge[1]); ?></span></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php
      $from = max(1, $page - 3);
      $to = min($pages, $page + 3);
      for ($i = $from; $i <= $to; $i++):
      ?>
        <?php if ($i === $page): ?>
          <span class="on"><?php echo e($i); ?></span>
        <?php else: ?>
          <a href="<?php echo e(url('schooladmin/alumni', array(
              'page' => $i, 'q' => $filters['search'],
              'state' => $filters['state'], 'dept' => $filters['department_id'],
          ))); ?>"><?php echo e($i); ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
