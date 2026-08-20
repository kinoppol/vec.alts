<?php
/**
 * Advisor worklist: who has answered, who still needs chasing.
 *
 * @var array $rows
 * @var array $counts   total / updated / pending
 * @var array $filters  current filter values
 * @var int $total      matching rows, for the pager
 * @var int $page
 * @var int $perPage
 * @var array $departments
 */
$cols = 'grid-template-columns:1.4fr 1fr 1.2fr .8fr';
$pages = (int) ceil($total / max(1, $perPage));
?>
<h1 class="page-title">ข้อมูลนักศึกษาในความดูแล</h1>
<p class="page-sub">
  นักศึกษาปัจจุบันและศิษย์เก่าที่อยู่ในความดูแลของคุณ
  · แบบสำรวจภาวะการมีงานทำกรอกได้เฉพาะผู้ที่สำเร็จการศึกษาแล้ว
  · ปีสำรวจ <?php echo e($filters['survey_year']); ?>
</p>

<div class="grid-4" style="margin-bottom:24px">
  <div class="card card-sm">
    <div class="kpi-value" style="font-size:28px"><?php echo e(num($counts['total'])); ?></div>
    <div class="stat-label" style="margin-top:0">ในความดูแลทั้งหมด</div>
  </div>
  <div class="card card-sm">
    <div class="kpi-value" style="font-size:28px;color:var(--ok)"><?php echo e(num($counts['studying'])); ?></div>
    <div class="stat-label" style="margin-top:0">กำลังศึกษา</div>
  </div>
  <div class="card card-sm">
    <div class="kpi-value" style="font-size:28px;color:var(--primary)"><?php echo e(num($counts['graduated'])); ?></div>
    <div class="stat-label" style="margin-top:0">ศิษย์เก่า</div>
  </div>
  <div class="card card-sm">
    <div class="kpi-value" style="font-size:28px"><?php echo e(num($counts['pending'])); ?></div>
    <div class="stat-label" style="margin-top:0">ศิษย์เก่าที่รอติดตาม</div>
  </div>
</div>

<div class="table">
  <form class="table-toolbar" method="get" action="<?php echo e(url()); ?>">
    <input type="hidden" name="r" value="advisor">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input class="input input-sm" type="search" name="q" placeholder="ค้นหาชื่อหรือรหัส"
             style="width:220px" value="<?php echo e($filters['search']); ?>">
      <select class="input input-sm" name="study" data-auto-submit style="width:150px"
              aria-label="กรองศิษย์ปัจจุบันหรือศิษย์เก่า">
        <option value="">ทั้งหมด</option>
        <?php foreach (study_states() as $code => $label): ?>
          <option value="<?php echo e($code); ?>"
                  <?php echo $filters['study_state'] === $code ? 'selected' : ''; ?>>
            <?php echo e($label); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select class="input input-sm" name="state" data-auto-submit style="width:150px">
        <option value="">ทุกสถานะการสำรวจ</option>
        <option value="pending" <?php echo $filters['state'] === 'pending' ? 'selected' : ''; ?>>รอติดตาม</option>
        <option value="updated" <?php echo $filters['state'] === 'updated' ? 'selected' : ''; ?>>อัปเดตแล้ว</option>
        <option value="unreachable" <?php echo $filters['state'] === 'unreachable' ? 'selected' : ''; ?>>ติดต่อไม่ได้</option>
      </select>
      <?php if ($departments): ?>
        <select class="input input-sm" name="dept" data-auto-submit style="width:170px">
          <option value="">ทุกสาขา</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?php echo e($dept['id']); ?>"
                    <?php echo (int) $filters['department_id'] === (int) $dept['id'] ? 'selected' : ''; ?>>
              <?php echo e($dept['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <button type="submit" class="btn btn-sm">ค้นหา</button>
    </div>
    <span class="cell-dim">พบ <?php echo e(num($total)); ?> รายการ</span>
  </form>

  <div class="table-head" style="<?php echo $cols; ?>">
    <span>ชื่อ - รหัส</span><span>สาขา</span><span>สถานะ</span><span></span>
  </div>

  <?php if (!$rows): ?>
    <div class="table-empty">ไม่พบนักศึกษาตามเงื่อนไขที่เลือก</div>
  <?php else: ?>
    <?php foreach ($rows as $row): ?>
      <?php
      // Current students are not behind on anything: the employment survey
      // only opens once they have finished, so they are labelled by where
      // they are rather than by a response they do not owe.
      $studying = arr($row, 'study_state', 'graduated') === 'studying';
      if ($studying) {
          $badge = array('ok', 'กำลังศึกษา');
      } elseif ($row['contact_state'] === 'unreachable') {
          $badge = array('warn', 'ติดต่อไม่ได้');
      } elseif ($row['employment_status'] === null) {
          $badge = array('wait', 'รอติดตาม');
      } elseif ((int) $row['is_draft'] === 1) {
          $badge = array('warn', 'บันทึกร่าง');
      } else {
          $badge = array('done', 'อัปเดตแล้ว');
      }
      ?>
      <div class="table-row" style="<?php echo $cols; ?>">
        <div>
          <div class="cell-title"><?php echo e(trim($row['title'] . $row['first_name'] . ' ' . $row['last_name'])); ?></div>
          <div class="cell-sub">รหัส <?php echo e($row['student_code']); ?></div>
        </div>
        <span class="cell-dim"><?php echo e($row['department_name'] !== null ? $row['department_name'] : '—'); ?></span>
        <span><span class="badge badge-<?php echo e($badge[0]); ?>"><?php echo e($badge[1]); ?></span></span>
        <span class="cell-actions" style="justify-self:end">
          <?php if ($studying): ?>
            <span class="cell-dim" style="font-size:12px">ยังไม่ถึงกำหนดสำรวจ</span>
          <?php else: ?>
            <a class="btn btn-sm" style="color:var(--primary)"
               href="<?php echo e(url('advisor/fill', array('id' => $row['id']))); ?>">กรอกแทน</a>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="on"><?php echo e($i); ?></span>
        <?php else: ?>
          <a href="<?php echo e(url('advisor', array(
              'page' => $i, 'q' => $filters['search'],
              'state' => $filters['state'], 'dept' => $filters['department_id'],
              'study' => $filters['study_state'],
          ))); ?>"><?php echo e($i); ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
