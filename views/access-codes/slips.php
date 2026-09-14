<?php
/**
 * Printable slips, one per person, carrying a freshly issued one-time code.
 *
 * Plain-coloured and cut-line friendly because they are printed and handed
 * out by hand; nothing on them but what the person needs to sign in once.
 *
 * @var array $batch slips / back / issuer / school
 * @var string $appName
 */
$slips = $batch['slips'];
?>
<style>
  body { background: #fff; color: #111; }
  .slip-page { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
  .slip-bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;
              margin-bottom: 18px; padding: 14px 16px; border: 1px solid #f0c36d; background: #fff8e6; border-radius: 10px; }
  .slip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
  .slip { border: 1.5px dashed #999; border-radius: 8px; padding: 14px 16px; break-inside: avoid; page-break-inside: avoid; }
  .slip h4 { margin: 0 0 2px; font-size: 13px; font-weight: 600; color: #555; }
  .slip .who { font-size: 15px; font-weight: 700; margin: 6px 0 2px; }
  .slip .meta { font-size: 12.5px; color: #555; }
  .slip .code { font: 700 24px/1.2 ui-monospace, Consolas, monospace; letter-spacing: 3px; margin: 10px 0 6px; }
  .slip ol { margin: 8px 0 0; padding-left: 18px; font-size: 12px; color: #333; }
  @media print {
    .slip-bar { display: none; }
    .slip-page { padding: 0; }
  }
</style>

<div class="slip-page">
  <div class="slip-bar">
    <div>
      <strong>ออกรหัสแล้ว <?php echo e(num(count($slips))); ?> คน</strong> ·
      หน้านี้แสดงได้ครั้งเดียว ระบบไม่เก็บรหัสไว้ในรูปที่อ่านได้ กรุณาพิมพ์หรือบันทึกก่อนออกจากหน้านี้
    </div>
    <div style="display:flex;gap:8px">
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">🖨 พิมพ์ใบแจ้งรหัส</button>
      <a class="btn btn-sm" href="<?php echo e(url($batch['back'])); ?>">กลับ</a>
    </div>
  </div>

  <div class="slip-grid">
    <?php foreach ($slips as $slip): ?>
      <div class="slip">
        <h4><?php echo e($appName); ?><?php echo $batch['school'] !== '' ? ' · ' . e($batch['school']) : ''; ?></h4>
        <div class="who"><?php echo e($slip['name']); ?></div>
        <div class="meta">
          รหัสนักศึกษา <?php echo e($slip['student_code']); ?>
          <?php echo $slip['department'] !== '' ? ' · ' . e($slip['department']) : ''; ?>
        </div>
        <div class="meta" style="margin-top:8px">รหัสเข้าใช้ครั้งแรก</div>
        <div class="code"><?php echo e(substr($slip['code'], 0, 4) . '-' . substr($slip['code'], 4)); ?></div>
        <div class="meta">ใช้ได้ถึง <?php echo e(thai_date($slip['expires_at'])); ?> · ใช้ได้ครั้งเดียว</div>
        <ol>
          <li>เข้าสู่ระบบ เลือก "เข้าใช้ครั้งแรก / ลืมรหัสผ่าน"</li>
          <li>กรอกรหัสนักศึกษา เลขบัตรประชาชน และรหัสด้านบน</li>
          <li>ตั้งรหัสผ่านของตนเอง แล้วใช้รหัสผ่านนั้นในครั้งต่อไป</li>
        </ol>
        <div class="meta" style="margin-top:8px">ห้ามให้ผู้อื่นดูหรือถ่ายรูปใบนี้</div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
