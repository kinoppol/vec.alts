<?php
/**
 * Inspect the people transferred in from RMS.
 *
 * @var array $rows
 * @var array $schools
 * @var array $groups
 * @var array|null $overview
 * @var array $filters
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $perPage
 */
$cols = 'grid-template-columns:1.6fr 1fr 1fr .9fr .6fr .8fr';
$states = study_states();
?>
<h1 class="page-title">ตรวจสอบข้อมูลผู้เรียน</h1>
<p class="page-sub">ดูรายชื่อที่โอนเข้ามาแล้ว ค้นหาและกรองตามระดับชั้น กลุ่มเรียน หรือสถานะ</p>

<?php if ($overview !== null): ?>
  <div class="grid-4" style="margin-bottom:22px">
    <div class="card card-sm">
      <div class="kpi-label">กำลังศึกษา</div>
      <div class="kpi-value" style="font-size:26px"><?php echo e(num($overview['studying'])); ?></div>
    </div>
    <div class="card card-sm">
      <div class="kpi-label">สำเร็จการศึกษา</div>
      <div class="kpi-value" style="font-size:26px"><?php echo e(num($overview['graduated'])); ?></div>
    </div>
    <div class="card card-sm">
      <div class="kpi-label">โอนจากระบบ RMS</div>
      <div class="kpi-value" style="font-size:26px"><?php echo e(num($overview['from_rms'])); ?></div>
    </div>
    <div class="card card-sm">
      <div class="kpi-label">ยังเข้าระบบไม่ได้</div>
      <div class="kpi-value" style="font-size:26px;<?php echo $overview['no_login'] > 0 ? 'color:var(--warn)' : ''; ?>">
        <?php echo e(num($overview['no_login'])); ?>
      </div>
      <div class="kpi-sub" style="color:var(--text-dim)">ไม่มีเลขบัตรประชาชน</div>
    </div>
  </div>
<?php endif; ?>

<div class="table">
  <form class="table-toolbar" method="get" action="<?php echo e(url()); ?>">
    <input type="hidden" name="r" value="centraladmin/students">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <select class="input input-sm" name="school_id" data-auto-submit style="width:200px">
        <option value="0">ทุกสถานศึกษา</option>
        <?php foreach ($schools as $school): ?>
          <option value="<?php echo e($school['id']); ?>"
                  <?php echo (int) $filters['school_id'] === (int) $school['id'] ? 'selected' : ''; ?>>
            <?php echo e($school['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input class="input input-sm" type="search" name="q" style="width:190px"
             placeholder="ชื่อ / รหัส / กลุ่ม / สาขา"
             value="<?php echo e($filters['search']); ?>">

      <select class="input input-sm" name="state" data-auto-submit style="width:150px">
        <option value="">ทุกสถานะ</option>
        <?php foreach ($states as $code => $label): ?>
          <option value="<?php echo e($code); ?>"
                  <?php echo $filters['study_state'] === $code ? 'selected' : ''; ?>>
            <?php echo e($label); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select class="input input-sm" name="level" data-auto-submit style="width:110px">
        <option value="">ทุกระดับ</option>
        <?php foreach (array('ปวช.', 'ปวส.') as $level): ?>
          <option value="<?php echo e($level); ?>"
                  <?php echo $filters['level'] === $level ? 'selected' : ''; ?>>
            <?php echo e($level); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if ($groups): ?>
        <select class="input input-sm" name="group" data-auto-submit style="width:190px">
          <option value="">ทุกกลุ่มเรียน</option>
          <?php foreach ($groups as $group): ?>
            <option value="<?php echo e($group['group_code']); ?>"
                    <?php echo $filters['group_code'] === $group['group_code'] ? 'selected' : ''; ?>>
              <?php echo e($group['group_code'] . ' · ' . $group['group_name'] . ' (' . $group['c'] . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <button type="submit" class="btn btn-sm">ค้นหา</button>
    </div>
    <span class="cell-dim">พบ <?php echo e(num($total)); ?> รายการ</span>
  </form>

  <div class="table-head" style="<?php echo $cols; ?>">
    <span>ชื่อ - รหัส</span><span>สาขา</span><span>กลุ่มเรียน</span>
    <span>ระดับ / ชั้นปี</span><span>GPAX</span><span>สถานะ</span>
  </div>

  <?php if (!$rows): ?>
    <div class="table-empty">
      ไม่พบข้อมูลตามเงื่อนไข —
      <a href="<?php echo e(url('centraladmin/import-students')); ?>">โอนข้อมูลนักเรียนจากระบบ RMS</a>
    </div>
  <?php else: ?>
    <?php foreach ($rows as $row): ?>
      <div class="table-row" style="<?php echo $cols; ?>">
        <div>
          <div class="cell-title"><?php echo e(trim($row['title'] . $row['first_name'] . ' ' . $row['last_name'])); ?></div>
          <div class="cell-sub">
            รหัส <?php echo e($row['student_code']); ?>
            <?php if ((string) $row['national_id_hash'] === ''): ?>
              · <span style="color:var(--warn)">ไม่มีเลขบัตร</span>
            <?php endif; ?>
          </div>
        </div>

        <span class="cell-dim">
          <?php
          $major = trim((string) $row['major_name']);
          if ($major === '' && $row['department_name'] !== null) {
              $major = $row['department_name'];
          }
          echo e($major !== '' ? $major : '—');
          ?>
        </span>

        <span class="cell-dim">
          <?php if (trim((string) $row['group_code']) !== ''): ?>
            <?php echo e($row['group_code']); ?>
            <?php if (trim((string) $row['group_name']) !== ''): ?>
              <br><span style="font-size:12px"><?php echo e($row['group_name']); ?></span>
            <?php endif; ?>
          <?php else: ?>
            —
          <?php endif; ?>
        </span>

        <span class="cell-dim">
          <?php echo e($row['level'] !== '' ? $row['level'] : '—'); ?>
          <?php if (trim((string) $row['grade_name']) !== ''): ?>
            <br><span style="font-size:12px"><?php echo e($row['grade_name']); ?></span>
          <?php endif; ?>
        </span>

        <span class="cell-dim"><?php echo e($row['gpax'] !== null ? $row['gpax'] : '—'); ?></span>

        <span>
          <?php if ($row['study_state'] === 'studying'): ?>
            <span class="badge badge-ok">กำลังศึกษา</span>
          <?php else: ?>
            <span class="badge badge-done">สำเร็จการศึกษา</span>
          <?php endif; ?>
          <?php if (trim((string) $row['status_name']) !== ''
                    && trim((string) $row['status_name']) !== 'กำลังศึกษา'): ?>
            <div class="cell-sub" style="margin-top:4px"><?php echo e($row['status_name']); ?></div>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php echo $this->partial('layout/pager', array(
      'route'   => 'centraladmin/students',
      'page'    => $page,
      'pages'   => $pages,
      'total'   => $total,
      'perPage' => $perPage,
      'params'  => array(
          'school_id' => $filters['school_id'],
          'q'         => $filters['search'],
          'state'     => $filters['study_state'],
          'level'     => $filters['level'],
          'group'     => $filters['group_code'],
      ),
  )); ?>
</div>
