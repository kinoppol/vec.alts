<?php
/**
 * Page links for a listing.
 *
 * @var string $route   route to link to, e.g. 'centraladmin/users'
 * @var int $page       current page, 1-based
 * @var int $pages      total number of pages
 * @var array $params   query parameters to carry across (without 'page')
 * @var int $total      optional, total matching rows
 * @var int $perPage    optional, rows per page
 */
if ($pages < 2) {
    return;
}
$params = isset($params) ? $params : array();

/** @return string */
$link = function ($number) use ($route, $params) {
    $all = $params;
    $all['page'] = $number;
    return url($route, $all);
};

// A window around the current page, so 30 pages do not print 30 links.
$from = max(1, $page - 2);
$to = min($pages, $page + 2);
?>
<nav class="pager" aria-label="แบ่งหน้า">

  <?php if ($page > 1): ?>
    <a href="<?php echo e($link($page - 1)); ?>" rel="prev" aria-label="หน้าก่อนหน้า">←</a>
  <?php else: ?>
    <span aria-hidden="true" style="opacity:.4">←</span>
  <?php endif; ?>

  <?php if ($from > 1): ?>
    <a href="<?php echo e($link(1)); ?>">1</a>
    <?php if ($from > 2): ?>
      <span style="border:none;opacity:.6">…</span>
    <?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $from; $i <= $to; $i++): ?>
    <?php if ($i === $page): ?>
      <span class="on" aria-current="page"><?php echo e($i); ?></span>
    <?php else: ?>
      <a href="<?php echo e($link($i)); ?>"><?php echo e($i); ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($to < $pages): ?>
    <?php if ($to < $pages - 1): ?>
      <span style="border:none;opacity:.6">…</span>
    <?php endif; ?>
    <a href="<?php echo e($link($pages)); ?>"><?php echo e($pages); ?></a>
  <?php endif; ?>

  <?php if ($page < $pages): ?>
    <a href="<?php echo e($link($page + 1)); ?>" rel="next" aria-label="หน้าถัดไป">→</a>
  <?php else: ?>
    <span aria-hidden="true" style="opacity:.4">→</span>
  <?php endif; ?>

  <?php if (isset($total) && isset($perPage)): ?>
    <span style="border:none;color:var(--text-dim);font-weight:500;margin-left:8px">
      <?php
      $first = (($page - 1) * $perPage) + 1;
      $last = min($total, $page * $perPage);
      echo e($first . '-' . $last . ' จาก ' . num($total));
      ?>
    </span>
  <?php endif; ?>

</nav>
