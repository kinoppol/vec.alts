<?php
/**
 * Signed-in application shell: sidebar + main column.
 *
 * @var string $content
 * @var Auth $auth
 * @var string $route current route, for highlighting the active menu entry
 */
$user = $auth->user();
$activeRoute = isset($route) ? $route : '';
$menu = app_menu($auth->role());
?><!doctype html>
<html lang="th">
<head>
<?php echo $this->partial('layout/head', isset($title) ? array('title' => $title) : array()); ?>
</head>
<body>
<div class="shell scr">

  <aside class="sidebar">
    <div class="sidebar-head">
      <div class="logo logo-sm">ศ</div>
      <div>
        <div class="sidebar-org"><?php echo e($user ? arr($user, 'school_name', 'ระบบกลาง') : ''); ?></div>
        <div class="sidebar-role"><?php echo e(role_label($auth->role())); ?></div>
      </div>
    </div>

    <nav>
      <?php foreach ($menu as $item): ?>
        <a class="nav-item<?php echo $item['route'] === $activeRoute ? ' on' : ''; ?>"
           href="<?php echo e(url($item['route'])); ?>"><?php echo e($item['label']); ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <button type="button" class="btn btn-sm" data-theme-toggle>
        <span data-theme-icon>🌙</span> โหมด<span data-theme-label>มืด</span>
      </button>
      <form method="post" action="<?php echo e(url('logout')); ?>">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-sm btn-block">ออกจากระบบ</button>
      </form>
    </div>
  </aside>

  <main class="main">
    <?php echo $this->partial('layout/flash'); ?>
    <?php echo $content; ?>
  </main>

</div>
<script src="<?php echo e(asset('js/app.js')); ?>?v=<?php echo e(VEC_VERSION); ?>"></script>
</body>
</html>
