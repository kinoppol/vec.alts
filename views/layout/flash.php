<?php
/**
 * Renders and clears queued flash messages.
 */
$messages = flash_take();
if (!$messages) {
    return;
}
$classes = array(
    'success' => 'alert alert-success',
    'error'   => 'alert alert-error',
    'warn'    => 'alert alert-warn',
    'info'    => 'alert alert-info',
);
foreach ($messages as $message) {
    $class = isset($classes[$message['type']]) ? $classes[$message['type']] : 'alert';
    echo '<div class="' . e($class) . '" role="alert">' . e($message['message']) . '</div>';
}
