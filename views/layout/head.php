<?php
/**
 * Shared <head>. Expects $title and optionally $appName.
 *
 * @var string $title
 * @var string $appName
 */
$pageTitle = isset($title) && $title !== '' ? $title : 'ระบบติดตามศิษย์เก่า';
$siteName = isset($appName) && $appName !== '' ? $appName : 'ระบบติดตามศิษย์เก่า';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo e($pageTitle); ?> · <?php echo e($siteName); ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&amp;family=IBM+Plex+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>?v=<?php echo e(VEC_VERSION); ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='24' fill='%237c3aed'/><text x='50' y='68' font-size='58' text-anchor='middle' fill='white' font-family='sans-serif'>&#3624;</text></svg>">

<script>
/* Set the theme before first paint so the page never flashes the wrong one. */
(function () {
    try {
        var stored = window.localStorage.getItem('vec-theme');
        if (!stored) {
            stored = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
                ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', stored);
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
    }
}());
</script>
