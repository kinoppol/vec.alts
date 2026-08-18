<?php
/**
 * @var string $route
 * @var Auth $auth
 */
?>
<div class="wrap" style="padding:72px 24px;text-align:center">
  <div style="font-size:64px;margin-bottom:12px">🔎</div>
  <h1 class="section-title">ไม่พบหน้าที่ต้องการ</h1>
  <p class="section-sub">เส้นทาง <code><?php echo e($route); ?></code> ไม่มีอยู่ในระบบ</p>
  <a class="btn btn-primary btn-lg"
     href="<?php echo e(url($auth->check() ? $auth->homeRoute() : 'home')); ?>">
    กลับหน้าหลัก
  </a>
</div>
