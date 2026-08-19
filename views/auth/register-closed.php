<?php
/**
 * Shown in place of the sign-up form when the central administrator has
 * closed institution registration.
 *
 * Deliberately says the sign-up is closed rather than pretending the page is
 * missing: a college that was told to register here needs to know it should
 * ask the central administrator, not that it mistyped the address.
 */
?>
<div class="scr" style="min-height:100vh;background:var(--bg);padding:40px 24px">
  <div style="max-width:520px;margin:0 auto">

    <a class="btn" href="<?php echo e(url('home')); ?>" style="margin-bottom:24px">← กลับ</a>

    <div class="card card-lg">
      <div style="font-size:38px;line-height:1;margin-bottom:14px">🔒</div>

      <h2 style="font-size:24px;font-weight:700;color:var(--text);margin-bottom:8px">
        ขณะนี้ปิดรับสมัครสถานศึกษาใหม่
      </h2>

      <p style="color:var(--text-dim);font-size:14px;line-height:1.7;margin-bottom:24px">
        ผู้ดูแลระบบกลางปิดการสมัครด้วยตนเองไว้ชั่วคราว
        หากสถานศึกษาของคุณต้องการเข้าใช้งานระบบ กรุณาติดต่อผู้ดูแลระบบกลางโดยตรง
        เพื่อขอให้เปิดบัญชีให้
      </p>

      <a class="btn btn-primary" href="<?php echo e(url('login')); ?>">เข้าสู่ระบบ</a>
    </div>

  </div>
</div>
