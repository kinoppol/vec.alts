<?php
/**
 * Every staff account across every institution.
 *
 * @var array $users
 * @var array $schools
 * @var array $filters
 */
$cols = 'grid-template-columns:1.5fr 1.3fr 1fr .9fr .8fr';
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
    <span class="cell-dim">พบ <?php echo e(num(count($users))); ?> รายการ</span>
  </form>

  <div class="table-head" style="<?php echo $cols; ?>">
    <span>ชื่อ</span><span>สถานศึกษา</span><span>บทบาท</span><span>เข้าใช้ล่าสุด</span><span>สถานะ</span>
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
        <span class="cell-dim"><?php echo e(role_label($user['role'])); ?></span>
        <span class="cell-dim" style="font-size:12px"><?php echo e(thai_date($user['last_login_at'])); ?></span>
        <span><span class="badge badge-<?php echo e($badge[0]); ?>"><?php echo e($badge[1]); ?></span></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
