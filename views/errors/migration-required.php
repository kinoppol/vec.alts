<?php
/**
 * Shown when the application code is newer than the database schema.
 *
 * Deliberately says nothing about tables or columns: this page is public.
 * The instructions are aimed at whoever deployed the release.
 *
 * @var Auth $auth
 */
?>
<div class="scr" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
  <div class="card card-lg" style="max-width:560px;text-align:center">

    <div style="font-size:52px;margin-bottom:12px">🛠️</div>

    <h1 style="font-size:22px;font-weight:700;color:var(--text);margin-bottom:10px">
      ระบบกำลังปรับปรุงฐานข้อมูล
    </h1>

    <p style="color:var(--text-dim);font-size:15px;line-height:1.7;margin-bottom:24px">
      ขออภัยในความไม่สะดวก ขณะนี้ระบบอยู่ระหว่างการปรับปรุง
      กรุณากลับมาใหม่อีกครั้งในอีกสักครู่
    </p>

    <div class="alert alert-info" style="text-align:left;margin-bottom:20px">
      <b>สำหรับผู้ดูแลระบบ</b><br>
      โปรแกรมถูกอัปเดตเป็นเวอร์ชันใหม่แล้ว แต่โครงสร้างฐานข้อมูลยังไม่ได้ปรับตาม
      กรุณาเข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบกลาง แล้วสั่ง “ปรับปรุงฐานข้อมูล”
      ที่เมนู Migration ฐานข้อมูล (หรือเปิด <code>install.php</code>)
    </div>

    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a class="btn btn-primary" href="<?php echo e(url('login', array('tab' => 'staff'))); ?>">
        เข้าสู่ระบบสำหรับผู้ดูแล
      </a>
      <a class="btn" href="<?php echo e(url('admin/migrations')); ?>">ไปที่เมนู Migration</a>
    </div>

  </div>
</div>
