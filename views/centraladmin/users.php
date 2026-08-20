<?php
/**
 * Every staff account across every institution.
 *
 * @var array $users
 * @var array $schools
 * @var array $filters
 * @var Auth $auth
 */
$cols = 'grid-template-columns:1.4fr 1fr 1.15fr 1.1fr .7fr 1fr';
$assignableRoles = staff_roles();
unset($assignableRoles['centraladmin']);
?>
<h1 class="page-title">ผู้ใช้งานระบบ</h1>
<p class="page-sub">บัญชีบุคลากรทั้งหมดในทุกสถานศึกษา</p>

<div class="table">
  <form class="table-toolbar" method="get" action="<?php echo e(url()); ?>">
    <input type="hidden" name="r" value="centraladmin/users">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input class="input input-sm" type="search" name="q" placeholder="ค้นหาชื่อหรืออีเมล"
             style="width:220px" value="<?php echo e($filters['search']); ?>">
      <select class="input input-sm" name="school" data-auto-submit style="width:220px">
        <option value="0">ทุกสถานศึกษา</option>
        <?php foreach ($schools as $school): ?>
          <option value="<?php echo e($school['id']); ?>"
                  <?php echo (int) $filters['school_id'] === (int) $school['id'] ? 'selected' : ''; ?>>
            <?php echo e($school['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-sm">ค้นหา</button>
    </div>
    <span class="cell-dim">พบ <?php echo e(num($total)); ?> รายการ</span>
  </form>

  <div class="table-head" style="<?php echo $cols; ?>">
    <span>ชื่อ</span><span>สถานศึกษา</span><span>บทบาท</span><span>กลุ่มที่ปรึกษา</span><span>เข้าใช้ล่าสุด</span><span>สถานะ</span>
  </div>

  <?php if (!$users): ?>
    <div class="table-empty">ไม่พบผู้ใช้งานตามเงื่อนไข</div>
  <?php else: ?>
    <?php foreach ($users as $user): ?>
      <?php
      if ($user['status'] === 'active') {
          $badge = array('done', 'ใช้งาน');
      } elseif ($user['status'] === 'pending') {
          $badge = array('warn', 'รออนุมัติ');
      } else {
          $badge = array('wait', 'ระงับ');
      }
      ?>
      <div class="table-row" style="<?php echo $cols; ?>">
        <div style="display:flex;align-items:center;gap:12px">
          <?php echo $this->partial('layout/avatar', array(
              'name' => $user['full_name'],
              'path' => arr($user, 'avatar_path', ''),
              'size' => 38,
          )); ?>
          <div>
            <div class="cell-title"><?php echo e($user['full_name']); ?></div>
            <div class="cell-sub"><?php echo e($user['email'] !== null && $user['email'] !== '' ? $user['email'] : 'ชื่อผู้ใช้: ' . arr($user, 'username', '—')); ?></div>
          </div>
        </div>
        <span class="cell-dim"><?php echo e($user['school_name'] !== null ? $user['school_name'] : 'ระบบกลาง'); ?></span>
        <span>
          <?php if ($user['role'] === 'centraladmin' || $user['school_id'] === null): ?>
            <span class="cell-dim"><?php echo e(role_label($user['role'])); ?></span>
            <?php if ($user['role'] === 'centraladmin'): ?>
              <div class="cell-sub">เปลี่ยนสิทธิ์ไม่ได้</div>
            <?php endif; ?>
          <?php elseif ((int) $user['id'] === $auth->id()): ?>
            <span class="cell-dim"><?php echo e(role_label($user['role'])); ?></span>
            <div class="cell-sub">บัญชีของคุณเอง</div>
          <?php else: ?>
            <form method="post" action="<?php echo e(url('centraladmin/user-role')); ?>"
                  style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo e($user['id']); ?>">
              <input type="hidden" name="q" value="<?php echo e($filters['search']); ?>">
              <input type="hidden" name="school" value="<?php echo e($filters['school_id']); ?>">
              <input type="hidden" name="page" value="<?php echo e($page); ?>">
              <select class="input input-sm" name="role" style="width:118px"
                      aria-label="สิทธิ์ของ <?php echo e($user['full_name']); ?>">
                <?php foreach ($assignableRoles as $code => $label): ?>
                  <option value="<?php echo e($code); ?>"
                          <?php echo $user['role'] === $code ? 'selected' : ''; ?>>
                    <?php echo e($label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm"
                      data-confirm="เปลี่ยนสิทธิ์ของ <?php echo e($user['full_name']); ?>?">บันทึก</button>
            </form>
          <?php endif; ?>
        </span>

        <span>
          <?php
          $groups = arr($advisorGroups, (int) $user['id'], array());
          if (!$groups):
          ?>
            <span class="cell-dim">—</span>
          <?php else: ?>
            <span style="display:flex;flex-wrap:wrap;gap:4px">
              <?php foreach (array_slice($groups, 0, 4) as $group): ?>
                <span class="badge badge-done" style="font-size:11px;padding:3px 9px"
                      title="<?php echo e($group['abbr'] !== '' ? $group['abbr'] . ' · ' : ''); ?>รหัส <?php echo e($group['code']); ?> · ภาค <?php echo e($group['semester'] . '/' . $group['year']); ?>">
                  <?php echo e($group['label']); ?>
                </span>
              <?php endforeach; ?>
              <?php if (count($groups) > 4): ?>
                <span class="cell-dim" style="font-size:11px;align-self:center"
                      title="<?php
                          $rest = array();
                          foreach (array_slice($groups, 4) as $group) {
                              $rest[] = $group['label'];
                          }
                          echo e(implode(', ', $rest));
                      ?>">+<?php echo e(count($groups) - 4); ?></span>
              <?php endif; ?>
            </span>
          <?php endif; ?>
        </span>

        <span class="cell-dim" style="font-size:12px"><?php echo e(thai_date($user['last_login_at'])); ?></span>
        <span style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span class="badge badge-<?php echo e($badge[0]); ?>"><?php echo e($badge[1]); ?></span>
          <?php if ($user['role'] !== 'centraladmin' && $user['status'] === 'active'): ?>
            <form method="post" action="<?php echo e(url('centraladmin/impersonate')); ?>" style="display:inline">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo e($user['id']); ?>">
              <button type="submit" class="btn btn-sm" style="color:var(--primary)"
                      data-confirm="เข้าใช้งานระบบในนาม <?php echo e($user['full_name']); ?>? การกระทำทั้งหมดจะถูกบันทึกในชื่อผู้ใช้รายนี้">
                สวมสิทธิ์
              </button>
            </form>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php echo $this->partial('layout/pager', array(
      'route'   => 'centraladmin/users',
      'page'    => $page,
      'pages'   => $pages,
      'total'   => $total,
      'perPage' => $perPage,
      'params'  => array('q' => $filters['search'], 'school' => $filters['school_id']),
  )); ?>
</div>
