<?php
/**
 * Layout for the signed-out marketing pages.
 *
 * @var string $content
 * @var Auth $auth
 */
?><!doctype html>
<html lang="th">
<head>
<?php echo $this->partial('layout/head', isset($title) ? array('title' => $title) : array()); ?>
</head>
<body>
<div class="scr">

<header class="site-header">
  <div class="site-header-inner">
    <a class="brand" href="<?php echo e(url('home')); ?>">
      <div class="logo">ศ</div>
      <div>
        <div class="brand-name"><?php echo e($appName); ?></div>
        <div class="brand-sub">VOCATIONAL ALUMNI TRACKING</div>
      </div>
    </a>
    <nav class="site-nav">
      <a href="<?php echo e(url('home')); ?>#overview">ภาพรวม</a>
      <a href="<?php echo e(url('home')); ?>#benefits">ประโยชน์</a>
      <a href="<?php echo e(url('home')); ?>#users">ผู้ใช้งาน</a>
    </nav>
    <div class="header-actions">
      <button type="button" class="icon-btn" data-theme-toggle title="สลับโหมดสว่าง-มืด">
        <span data-theme-icon>🌙</span>
      </button>
      <?php if ($auth->check()): ?>
        <a class="btn" href="<?php echo e(url($auth->homeRoute())); ?>">เข้าสู่พื้นที่ทำงาน</a>
      <?php else: ?>
        <a class="btn" href="<?php echo e(url('login')); ?>">เข้าสู่ระบบ</a>
        <?php if ($repo->registrationOpen()): ?>
          <a class="btn btn-primary" href="<?php echo e(url('register')); ?>">สมัครใช้งาน</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php echo $content; ?>

<footer class="site-footer">
  <div class="site-footer-inner">
    <span>© <?php echo e(current_academic_year()); ?> ระบบติดตามผู้สำเร็จการศึกษา สายอาชีวศึกษา</span>
    <span>รองรับหลายสถานศึกษา · โหมดมืด-สว่างตามระบบ</span>
  </div>
</footer>

</div>
<script src="<?php echo e(asset('js/app.js')); ?>?v=<?php echo e(VEC_VERSION); ?>"></script>
</body>
</html>
