<?php
/**
 * Staff account management for one institution.
 *
 * @var array $users
 * @var array $departments
 * @var array $old
 * @var bool $showForm
 */
$cols = 'grid-template-columns:1.4fr 1.2fr 1fr .8fr';
$old = isset($old) ? $old : array();
$roles = staff_roles();
unset($roles['centraladmin']); // only the central admin creates central admins
?>
<h1 class="page-title">ผู้ดูแลระบบสถานศึกษา</h1>
<p class="page-sub">จัดการผู้ใช้งานและข้อมูลของ<?php echo e($school['name']); ?></p>

<div style="display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap">
  <a class="btn btn-primary" href="<?php echo e(url('schooladmin', array('new' => 1))); ?>">+ เพิ่มผู้ใช้งาน</a>
  <a class="btn" href="<?php echo e(url('schooladmin/import')); ?>">นำเข้าข้อมูลศิษย์เก่า (CSV)</a>
</div>

<?php if ($showForm): ?>
  <div class="card card-lg" style="margin-bottom:22px">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:18px">เพิ่มผู้ใช้งานใหม่</h3>
    <form method="post" action="<?php echo e(url('schooladmin/user-create')); ?>">
      <?php echo csrf_field(); ?>
      <div class="grid-2">
        <div>
          <label class="label" for="full_name">ชื่อ-นามสกุล *</label>
          <input class="input" type="text" id="full_name" name="full_name" required
                 value="<?php echo e(arr($old, 'full_name', '')); ?>">
        </div>
        <div>
          <label class="label" for="email">อีเมล *</label>
          <input class="input" type="email" id="email" name="email" required
                 value="<?php echo e(arr($old, 'email', '')); ?>">
        </div>
        <div>
          <label class="label" for="role">บทบาท *</label>
          <select class="input" id="role" name="role">
            <?php foreach ($roles as $code => $label): ?>
              <option value="<?php echo e($code); ?>"
                      <?php echo arr($old, 'role', '') === $code ? 'selected' : ''; ?>>
                <?php echo e($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label" for="phone">เบอร์โทรศัพท์</label>
          <input class="input" type="text" id="phone" name="phone"
                 value="<?php echo e(arr($old, 'phone', '')); ?>">
        </div>
        <div>
          <label class="label" for="password">รหัสผ่านเริ่มต้น *</label>
          <input class="input" type="text" id="password" name="password" required minlength="8">
          <div class="hint">อย่างน้อย 8 ตัวอักษร แจ้งให้ผู้ใช้เปลี่ยนเมื่อเข้าใช้ครั้งแรก</div>
        </div>
        <div>
          <label class="label" for="department_id">สาขาที่ดูแล</label>
          <select class="input" id="department_id" name="department_id">
            <option value="">— ไม่ระบุ —</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?php echo e($dept['id']); ?>"><?php echo e($dept['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <a class="btn" href="<?php echo e(url('schooladmin')); ?>">ยกเลิก</a>
        <button type="submit" class="btn btn-primary">บันทึกผู้ใช้งาน</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="table">
  <div class="table-head" style="<?php echo $cols; ?>">
    <span>ชื่อ</span><span>บทบาท</span><span>สถานะ</span><span></span>
  </div>

  <?php if (!$users): ?>
    <div class="table-empty">ยังไม่มีผู้ใช้งานอื่นในสถานศึกษานี้</div>
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
        <span class="cell-dim">
          <?php echo e(role_label($user['role'])); ?>
          <?php if ($user['department_name'] !== null): ?>
            <br><span style="font-size:12px">· <?php echo e($user['department_name']); ?></span>
          <?php endif; ?>
        </span>
        <span><span class="badge badge-<?php echo e($badge[0]); ?>"><?php echo e($badge[1]); ?></span></span>
        <span class="cell-actions" style="justify-self:end">
          <form method="post" action="<?php echo e(url('schooladmin/user-status')); ?>"
                style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo e($user['id']); ?>">
            <?php if ($user['status'] === 'active'): ?>
              <button type="submit" name="status" value="suspended" class="btn btn-sm"
                      data-confirm="ระงับการใช้งานบัญชีนี้?">ระงับ</button>
            <?php else: ?>
              <button type="submit" name="status" value="active" class="btn btn-sm"
                      style="color:var(--primary)">เปิดใช้งาน</button>
            <?php endif; ?>
          </form>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php echo $this->partial('layout/pager', array(
      'route'   => 'schooladmin',
      'page'    => $page,
      'pages'   => $pages,
      'total'   => $total,
      'perPage' => $perPage,
      'params'  => array(),
  )); ?>
</div>
