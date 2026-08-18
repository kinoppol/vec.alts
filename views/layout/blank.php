<?php
/**
 * Bare layout for the split-screen login/register pages, which bring their
 * own chrome.
 *
 * @var string $content
 */
?><!doctype html>
<html lang="th">
<head>
<?php echo $this->partial('layout/head', isset($title) ? array('title' => $title) : array()); ?>
</head>
<body>
<?php echo $content; ?>
<script src="<?php echo e(asset('js/app.js')); ?>?v=<?php echo e(VEC_VERSION); ?>"></script>
</body>
</html>
