<?php
/**
 * Installer UI. Rendered by install.php, which supplies every variable used
 * here. Self-contained styling, because the installer must render correctly
 * even before the application is configured.
 *
 * @var string $step
 * @var array $notices
 * @var array $checks
 * @var bool $checksPass
 * @var array $dbForm
 * @var array $config
 * @var bool $isInstalled
 * @var array $migrationRows
 * @var int $pendingCount
 * @var array|null $dbInfo
 */

$steps = array(
    'requirements' => 'ตรวจสอบระบบ',
    'database'     => 'ฐานข้อมูล',
    'admin'        => 'ผู้ดูแลระบบ',
    'done'         => 'เสร็จสิ้น',
);
$stepOrder = array_keys($steps);
$currentIndex = array_search($step, $stepOrder, true);
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ติดตั้งระบบติดตามศิษย์เก่า</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<style>
:root{--bg:#f6f5fb;--surface:#fff;--surface-2:#f0eef9;--text:#1a1830;--text-dim:#6b6880;
  --border:#e6e2f2;--primary:#7c3aed;--primary-2:#2563eb;--ok:#059669;--warn:#c98a00;--danger:#dc2626}
@media (prefers-color-scheme: dark){
  :root{--bg:#0f0e1a;--surface:#1a1830;--surface-2:#232140;--text:#f0eef8;--text-dim:#9b98b5;
    --border:#2d2a4a;--primary:#9d7bf0;--primary-2:#5b8def;--ok:#34d399;--warn:#e0a000;--danger:#f87171}
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'IBM Plex Sans Thai',system-ui,-apple-system,'Segoe UI',Tahoma,sans-serif;
  background:var(--bg);color:var(--text);padding:40px 20px;line-height:1.5;-webkit-font-smoothing:antialiased}
.wrap{max-width:780px;margin:0 auto}
.head{display:flex;align-items:center;gap:14px;margin-bottom:28px}
.logo{width:48px;height:48px;border-radius:13px;background:linear-gradient(135deg,var(--primary),var(--primary-2));
  display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:22px}
h1{font-size:22px;font-weight:700}
.sub{font-size:13px;color:var(--text-dim)}
.steps{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.stepchip{padding:7px 14px;border-radius:999px;font-size:13px;font-weight:600;
  background:var(--surface-2);color:var(--text-dim)}
.stepchip.on{background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff}
.stepchip.done{background:var(--surface-2);color:var(--primary)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:28px;margin-bottom:20px}
h2{font-size:18px;font-weight:700;margin-bottom:6px}
h3{font-size:15px;font-weight:700;margin-bottom:12px}
.muted{color:var(--text-dim);font-size:14px;margin-bottom:20px}
.alert{padding:13px 16px;border-radius:11px;font-size:14px;margin-bottom:12px;font-weight:500}
.alert-success{background:rgba(5,150,105,.14);color:var(--ok)}
.alert-error{background:rgba(220,38,38,.12);color:var(--danger)}
.alert-warn{background:rgba(224,160,0,.18);color:var(--warn)}
.alert-info{background:rgba(124,58,237,.12);color:var(--primary)}
label.lbl{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
.inp{width:100%;padding:11px 14px;border-radius:10px;border:1px solid var(--border);
  background:var(--bg);color:var(--text);font-size:15px;font-family:inherit}
.inp:focus{outline:none;border-color:var(--primary)}
.field{margin-bottom:16px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.hint{font-size:12px;color:var(--text-dim);margin-top:5px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;
  border-radius:11px;border:1px solid var(--border);background:var(--surface);color:var(--text);
  font-weight:600;font-size:14px;cursor:pointer;font-family:inherit;text-decoration:none}
.btn:hover{filter:brightness(.97)}
.btn-primary{border:none;background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff}
.btn-danger{border-color:var(--danger);color:var(--danger)}
.btn-block{width:100%}
.checks{list-style:none}
.checks li{display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid var(--border)}
.checks li:last-child{border-bottom:none}
.mark{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:700;flex-shrink:0;color:#fff;margin-top:1px}
.mark.ok{background:var(--ok)}.mark.no{background:var(--danger)}.mark.warn{background:var(--warn)}
.checks .name{font-weight:600;font-size:14px}
.checks .detail{font-size:12px;color:var(--text-dim)}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--border)}
th{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-dim)}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600}
.b-ok{background:rgba(124,58,237,.14);color:var(--primary)}
.b-wait{background:rgba(224,160,0,.18);color:var(--warn)}
.b-no{background:rgba(220,38,38,.12);color:var(--danger)}
.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}
dl.kv{display:grid;grid-template-columns:auto 1fr;gap:6px 18px;font-size:13px}
dl.kv dt{color:var(--text-dim)}
dl.kv dd{font-weight:600}
code{background:var(--surface-2);padding:2px 6px;border-radius:5px;font-size:12px}
.danger-zone{border-color:var(--danger)}
@media (max-width:640px){.grid2{grid-template-columns:1fr}body{padding:24px 14px}}
</style>
</head>
<body>
<div class="wrap">

  <div class="head">
    <div class="logo">ศ</div>
    <div>
      <h1>ติดตั้งระบบติดตามศิษย์เก่า</h1>
      <div class="sub">
        PHP <?php echo e(PHP_VERSION); ?>
        <?php if ($isInstalled): ?> · ติดตั้งแล้ว<?php endif; ?>
        <?php if ($isInstalled && !empty($_SESSION['install_auth'])): ?>
          · <a href="<?php echo e(install_url(array('signout' => 1))); ?>">ออกจากโหมดผู้ดูแล</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!$isInstalled && $currentIndex !== false): ?>
    <div class="steps">
      <?php foreach ($steps as $key => $label): ?>
        <?php
        $index = array_search($key, $stepOrder, true);
        $class = 'stepchip';
        if ($key === $step) {
            $class .= ' on';
        } elseif ($index < $currentIndex) {
            $class .= ' done';
        }
        ?>
        <span class="<?php echo e($class); ?>"><?php echo e(($index + 1) . '. ' . $label); ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php foreach ($notices as $item): ?>
    <div class="alert alert-<?php echo e($item['type']); ?>"><?php echo e($item['message']); ?></div>
  <?php endforeach; ?>

  <?php // =================================================== unlock ?>
  <?php if ($step === 'unlock'): ?>
    <div class="card">
      <h2>ระบบนี้ติดตั้งไว้แล้ว</h2>
      <p class="muted">
        เพื่อความปลอดภัย ต้องยืนยันตัวตนด้วยบัญชีผู้ดูแลระบบกลางก่อนจึงจะเข้าใช้เครื่องมือติดตั้งซ้ำได้
      </p>
      <form method="post" action="<?php echo e(install_url()); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="unlock">
        <div class="field">
          <label class="lbl" for="email">อีเมลผู้ดูแลระบบกลาง</label>
          <input class="inp" type="email" id="email" name="email" required autofocus>
        </div>
        <div class="field">
          <label class="lbl" for="password">รหัสผ่าน</label>
          <input class="inp" type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">เข้าสู่เครื่องมือติดตั้ง</button>
      </form>
      <p class="hint" style="margin-top:16px">
        ลืมรหัสผ่านหรือเชื่อมต่อฐานข้อมูลไม่ได้? สร้างไฟล์เปล่าชื่อ
        <code>config/install.unlock</code> บนเซิร์ฟเวอร์ แล้วโหลดหน้านี้ใหม่
        (อย่าลืมลบไฟล์นั้นทิ้งเมื่อทำงานเสร็จ)
      </p>
    </div>

  <?php // ============================================= requirements ?>
  <?php elseif ($step === 'requirements'): ?>
    <div class="card">
      <h2>ตรวจสอบความพร้อมของเซิร์ฟเวอร์</h2>
      <p class="muted">
        ระบบนี้ทำงานได้ทั้งบน PHP 5.4 (CentOS 7) และ PHP 8 (XAMPP)
        รวมถึงฐานข้อมูล MySQL 5 และ MariaDB 10
      </p>
      <ul class="checks">
        <?php foreach ($checks as $check): ?>
          <li>
            <?php if ($check['ok']): ?>
              <span class="mark ok">✓</span>
            <?php elseif ($check['required']): ?>
              <span class="mark no">✕</span>
            <?php else: ?>
              <span class="mark warn">!</span>
            <?php endif; ?>
            <div>
              <div class="name"><?php echo e($check['label']); ?></div>
              <div class="detail"><?php echo e($check['detail']); ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="actions">
        <?php if ($checksPass): ?>
          <a class="btn btn-primary" href="<?php echo e(install_url(array('step' => 'database'))); ?>">
            ถัดไป: ตั้งค่าฐานข้อมูล →
          </a>
        <?php else: ?>
          <a class="btn" href="<?php echo e(install_url()); ?>">ตรวจสอบอีกครั้ง</a>
        <?php endif; ?>
      </div>
    </div>

  <?php // ================================================ database ?>
  <?php elseif ($step === 'database'): ?>
    <div class="card">
      <h2>ตั้งค่าการเชื่อมต่อฐานข้อมูล</h2>
      <p class="muted">
        ระบบจะทดสอบการเชื่อมต่อก่อนบันทึก แล้วสร้างตารางให้อัตโนมัติ
        ถ้ารันซ้ำ จะข้ามตารางที่มีอยู่แล้วโดยไม่กระทบข้อมูลเดิม
      </p>

      <form method="post" action="<?php echo e(install_url()); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save-database">

        <div class="grid2">
          <div class="field">
            <label class="lbl" for="db_host">เซิร์ฟเวอร์ฐานข้อมูล</label>
            <input class="inp" type="text" id="db_host" name="db_host" required
                   value="<?php echo e(arr($dbForm, 'host', 'localhost')); ?>">
          </div>
          <div class="field">
            <label class="lbl" for="db_port">พอร์ต</label>
            <input class="inp" type="number" id="db_port" name="db_port"
                   value="<?php echo e(arr($dbForm, 'port', 3306)); ?>">
          </div>
        </div>

        <div class="field">
          <label class="lbl" for="db_name">ชื่อฐานข้อมูล</label>
          <input class="inp" type="text" id="db_name" name="db_name" required
                 value="<?php echo e(arr($dbForm, 'name', 'vec_alumni')); ?>">
        </div>

        <div class="grid2">
          <div class="field">
            <label class="lbl" for="db_user">ชื่อผู้ใช้</label>
            <input class="inp" type="text" id="db_user" name="db_user" required
                   value="<?php echo e(arr($dbForm, 'user', 'root')); ?>">
          </div>
          <div class="field">
            <label class="lbl" for="db_pass">รหัสผ่าน</label>
            <input class="inp" type="password" id="db_pass" name="db_pass"
                   value="<?php echo e(arr($dbForm, 'pass', '')); ?>">
            <div class="hint">XAMPP มักเว้นว่าง</div>
          </div>
        </div>

        <div class="grid2">
          <div class="field">
            <label class="lbl" for="db_prefix">คำนำหน้าตาราง</label>
            <input class="inp" type="text" id="db_prefix" name="db_prefix"
                   value="<?php echo e(arr($dbForm, 'prefix', 'va_')); ?>">
            <div class="hint">ใช้เมื่อต้องแชร์ฐานข้อมูลกับระบบอื่น</div>
          </div>
          <div class="field">
            <label class="lbl" for="db_socket">Unix socket</label>
            <input class="inp" type="text" id="db_socket" name="db_socket"
                   placeholder="/var/lib/mysql/mysql.sock"
                   value="<?php echo e(arr($dbForm, 'socket', '')); ?>">
            <div class="hint">เว้นว่างถ้าเชื่อมต่อผ่าน TCP (Windows/XAMPP)</div>
          </div>
        </div>

        <div class="field">
          <label class="lbl">
            <input type="checkbox" name="db_create" value="1" checked>
            สร้างฐานข้อมูลให้อัตโนมัติถ้ายังไม่มี
          </label>
        </div>

        <h3 style="margin-top:24px">ค่าทั่วไปของระบบ</h3>
        <div class="field">
          <label class="lbl" for="app_name">ชื่อระบบ</label>
          <input class="inp" type="text" id="app_name" name="app_name"
                 value="<?php echo e($config['app']['name']); ?>">
        </div>
        <div class="grid2">
          <div class="field">
            <label class="lbl" for="app_env">โหมดการทำงาน</label>
            <select class="inp" id="app_env" name="app_env">
              <option value="production" <?php echo $config['app']['env'] === 'production' ? 'selected' : ''; ?>>
                Production (ซ่อนรายละเอียดข้อผิดพลาด)
              </option>
              <option value="development" <?php echo $config['app']['env'] === 'development' ? 'selected' : ''; ?>>
                Development (แสดงรายละเอียดข้อผิดพลาด)
              </option>
            </select>
          </div>
          <div class="field">
            <label class="lbl" for="app_timezone">เขตเวลา</label>
            <input class="inp" type="text" id="app_timezone" name="app_timezone"
                   value="<?php echo e($config['app']['timezone']); ?>">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
          ทดสอบการเชื่อมต่อและสร้างตาราง
        </button>
      </form>
    </div>

  <?php // =================================================== admin ?>
  <?php elseif ($step === 'admin'): ?>
    <div class="card">
      <h2>สร้างบัญชีผู้ดูแลระบบกลาง</h2>
      <p class="muted">
        บัญชีนี้ใช้อนุมัติสถานศึกษาที่สมัครเข้ามา และจัดการ Migration ของฐานข้อมูล
        ถ้าอีเมลนี้มีอยู่แล้ว ระบบจะตั้งรหัสผ่านใหม่ให้แทนการสร้างซ้ำ
      </p>

      <form method="post" action="<?php echo e(install_url()); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save-admin">

        <div class="field">
          <label class="lbl" for="admin_name">ชื่อ-นามสกุล</label>
          <input class="inp" type="text" id="admin_name" name="admin_name" required
                 value="<?php echo e(post('admin_name', '')); ?>">
        </div>
        <div class="field">
          <label class="lbl" for="admin_email">อีเมล (ใช้เข้าสู่ระบบ)</label>
          <input class="inp" type="email" id="admin_email" name="admin_email" required
                 value="<?php echo e(post('admin_email', '')); ?>">
        </div>
        <div class="grid2">
          <div class="field">
            <label class="lbl" for="admin_password">รหัสผ่าน</label>
            <input class="inp" type="password" id="admin_password" name="admin_password"
                   required minlength="8">
            <div class="hint">อย่างน้อย 8 ตัวอักษร</div>
          </div>
          <div class="field">
            <label class="lbl" for="admin_password_confirm">ยืนยันรหัสผ่าน</label>
            <input class="inp" type="password" id="admin_password_confirm"
                   name="admin_password_confirm" required minlength="8">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">บันทึกและติดตั้งให้เสร็จสิ้น</button>
      </form>
    </div>

  <?php // ==================================================== done ?>
  <?php elseif ($step === 'done'): ?>
    <div class="card">
      <h2>ติดตั้งเสร็จสมบูรณ์</h2>
      <p class="muted">เข้าใช้งานได้ทันทีด้วยบัญชีผู้ดูแลระบบกลางที่เพิ่งสร้าง</p>

      <div class="alert alert-warn">
        เพื่อความปลอดภัยของเครื่องให้บริการจริง ควรลบหรือเปลี่ยนชื่อไฟล์ <code>install.php</code>
        เมื่อไม่ต้องใช้แล้ว หากยังเก็บไว้ ระบบจะขอรหัสผ่านผู้ดูแลระบบกลางทุกครั้งที่เปิด
      </div>

      <div class="actions">
        <a class="btn btn-primary" href="index.php">เข้าสู่ระบบ →</a>
        <a class="btn" href="<?php echo e(install_url(array('step' => 'manage'))); ?>">
          เครื่องมือดูแลระบบ
        </a>
      </div>
    </div>

  <?php // ================================================== manage ?>
  <?php elseif ($step === 'manage'): ?>

    <?php if ($dbInfo !== null): ?>
      <div class="card">
        <h3>สภาพแวดล้อมปัจจุบัน</h3>
        <dl class="kv">
          <dt>PHP</dt><dd><?php echo e(PHP_VERSION); ?></dd>
          <dt>ฐานข้อมูล</dt><dd><?php echo e($dbInfo['flavour'] . ' ' . $dbInfo['version']); ?></dd>
          <dt>ชุดอักขระ</dt><dd><?php echo e($dbInfo['charset'] . ' / ' . $dbInfo['collation']); ?></dd>
          <dt>ฐานข้อมูล</dt><dd><?php echo e($config['db']['name']); ?></dd>
          <dt>คำนำหน้าตาราง</dt><dd><?php echo e($config['db']['prefix'] !== '' ? $config['db']['prefix'] : '(ไม่มี)'); ?></dd>
          <dt>Batch ล่าสุด</dt><dd><?php echo e($dbInfo['batch']); ?></dd>
        </dl>
      </div>
    <?php endif; ?>

    <div class="card">
      <h2>Migration ฐานข้อมูล</h2>
      <p class="muted">
        <?php if ($pendingCount > 0): ?>
          มี migration รอปรับปรุง <?php echo e($pendingCount); ?> รายการ
        <?php else: ?>
          โครงสร้างฐานข้อมูลเป็นปัจจุบันแล้ว
        <?php endif; ?>
      </p>

      <?php if ($migrationRows): ?>
        <table>
          <tr><th>เวอร์ชัน</th><th>รายละเอียด</th><th>เมื่อ</th><th>สถานะ</th></tr>
          <?php foreach ($migrationRows as $row): ?>
            <tr>
              <td><?php echo e($row['version']); ?></td>
              <td><?php echo e($row['name']); ?></td>
              <td><?php echo e($row['applied_at'] !== null ? $row['applied_at'] : '—'); ?></td>
              <td>
                <?php if ($row['state'] === 'applied'): ?>
                  <span class="badge b-ok">ใช้งานแล้ว</span>
                <?php elseif ($row['state'] === 'pending'): ?>
                  <span class="badge b-wait">รอปรับปรุง</span>
                <?php else: ?>
                  <span class="badge b-no">ไม่พบไฟล์</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

      <div class="actions" style="margin-top:18px">
        <form method="post" action="<?php echo e(install_url()); ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="maintenance">
          <button type="submit" name="task" value="migrate" class="btn btn-primary">
            ปรับปรุงฐานข้อมูล
          </button>
        </form>
        <form method="post" action="<?php echo e(install_url()); ?>"
              onsubmit="return confirm('ย้อนกลับ migration ชุดล่าสุด? ข้อมูลในตารางหรือคอลัมน์ที่ถูกลบจะหายไป');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="maintenance">
          <button type="submit" name="task" value="rollback" class="btn">ย้อนกลับชุดล่าสุด</button>
        </form>
      </div>
    </div>

    <div class="card">
      <h2>เครื่องมืออื่น</h2>

      <h3 style="margin-top:8px">ตั้งรหัสผ่านใหม่</h3>
      <form method="post" action="<?php echo e(install_url()); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="maintenance">
        <input type="hidden" name="task" value="reset-admin">
        <div class="grid2">
          <div class="field">
            <label class="lbl" for="reset_email">อีเมลของบัญชี</label>
            <input class="inp" type="email" id="reset_email" name="reset_email" required>
          </div>
          <div class="field">
            <label class="lbl" for="reset_password">รหัสผ่านใหม่</label>
            <input class="inp" type="text" id="reset_password" name="reset_password"
                   required minlength="8">
          </div>
        </div>
        <button type="submit" class="btn">ตั้งรหัสผ่านใหม่</button>
      </form>

      <h3 style="margin-top:28px">ข้อมูลตัวอย่าง</h3>
      <p class="muted" style="margin-bottom:12px">
        สร้างสถานศึกษา ศิษย์เก่า และคำตอบแบบสำรวจชุดตัวอย่าง เพื่อทดลองใช้งานทุกหน้าจอ
        เหมาะกับเครื่องทดสอบ ไม่ควรใช้บนเครื่องให้บริการจริง
      </p>
      <form method="post" action="<?php echo e(install_url()); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="maintenance">
        <button type="submit" name="task" value="seed" class="btn">สร้างข้อมูลตัวอย่าง</button>
      </form>

      <h3 style="margin-top:28px">แก้ไขการเชื่อมต่อฐานข้อมูล</h3>
      <div class="actions">
        <a class="btn" href="<?php echo e(install_url(array('step' => 'database'))); ?>">
          เปิดฟอร์มตั้งค่าฐานข้อมูล
        </a>
        <a class="btn" href="<?php echo e(install_url(array('step' => 'admin'))); ?>">
          เพิ่ม/แก้ไขผู้ดูแลระบบกลาง
        </a>
      </div>
    </div>

    <div class="card danger-zone">
      <h2 style="color:var(--danger)">ติดตั้งใหม่ทั้งหมด</h2>
      <p class="muted">
        ลบตารางทั้งหมดของระบบนี้แล้วสร้างใหม่จาก migration
        <b>ข้อมูลทุกอย่างจะหายถาวร</b> รวมถึงบัญชีผู้ใช้และคำตอบแบบสำรวจ
        พิมพ์คำว่า <code>REINSTALL</code> เพื่อยืนยัน
      </p>
      <form method="post" action="<?php echo e(install_url()); ?>"
            onsubmit="return confirm('ยืนยันลบข้อมูลทั้งหมดและติดตั้งใหม่?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="maintenance">
        <input type="hidden" name="task" value="reinstall">
        <div class="field">
          <label class="lbl" for="confirm">พิมพ์ REINSTALL เพื่อยืนยัน</label>
          <input class="inp" type="text" id="confirm" name="confirm" autocomplete="off"
                 placeholder="REINSTALL">
        </div>
        <button type="submit" class="btn btn-danger">ลบข้อมูลและติดตั้งใหม่</button>
      </form>
    </div>

  <?php endif; ?>

  <p style="text-align:center;font-size:12px;color:var(--text-dim);margin-top:24px">
    ระบบติดตามผู้สำเร็จการศึกษา สายอาชีวศึกษา · เวอร์ชัน <?php echo e(VEC_VERSION); ?>
  </p>

</div>
</body>
</html>
