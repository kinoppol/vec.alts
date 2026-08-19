<?php
/**
 * Profile picture, falling back to the person's initial.
 *
 * @var string $name
 * @var string $path   users.avatar_path
 * @var string $size   optional CSS size, defaults to the .avatar class
 */
$url = avatar_url(isset($path) ? $path : '');
$style = isset($size) && $size !== ''
    ? 'width:' . (int) $size . 'px;height:' . (int) $size . 'px;font-size:' . round($size * 0.4) . 'px'
    : '';
?>
<?php if ($url !== ''): ?>
  <img class="avatar" src="<?php echo e($url); ?>" alt=""
       style="object-fit:cover<?php echo $style !== '' ? ';' . e($style) : ''; ?>">
<?php else: ?>
  <div class="avatar"<?php echo $style !== '' ? ' style="' . e($style) . '"' : ''; ?>><?php
      echo e(initials(isset($name) ? $name : ''));
  ?></div>
<?php endif; ?>
