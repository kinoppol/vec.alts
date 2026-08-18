<?php
/**
 * Every survey year this alumnus has answered.
 *
 * @var array $alumni
 * @var array $history
 */
?>
<h1 class="page-title">ประวัติการอัปเดต</h1>
<p class="page-sub">บันทึกสถานะที่คุณเคยส่งไว้ในแต่ละปีสำรวจ</p>

<div class="table">
  <div class="table-head" style="grid-template-columns:.7fr 1.3fr 1.6fr 1fr">
    <span>ปีสำรวจ</span><span>สถานะ</span><span>รายละเอียด</span><span>ส่งเมื่อ</span>
  </div>

  <?php if (!$history): ?>
    <div class="table-empty">ยังไม่มีประวัติการอัปเดต — กรอกข้อมูลครั้งแรกได้ที่เมนู “ข้อมูลของฉัน”</div>
  <?php else: ?>
    <?php foreach ($history as $row): ?>
      <div class="table-row" style="grid-template-columns:.7fr 1.3fr 1.6fr 1fr">
        <span class="cell-title"><?php echo e($row['survey_year']); ?></span>
        <span>
          <?php if ((int) $row['is_draft'] === 1): ?>
            <span class="badge badge-warn">ร่าง</span>
          <?php else: ?>
            <span class="badge badge-done"><?php echo e(employment_label($row['employment_status'])); ?></span>
          <?php endif; ?>
        </span>
        <span class="cell-dim">
          <?php
          $detail = '';
          if ($row['company_name'] !== '') {
              $detail = $row['company_name'];
              if ($row['job_position'] !== '') {
                  $detail .= ' · ' . $row['job_position'];
              }
          } elseif ($row['study_place'] !== '') {
              $detail = $row['study_place'];
              if ($row['study_major'] !== '') {
                  $detail .= ' · ' . $row['study_major'];
              }
          } elseif ($row['note'] !== null && $row['note'] !== '') {
              $detail = $row['note'];
          }
          echo e($detail !== '' ? $detail : '—');
          ?>
        </span>
        <span class="cell-dim"><?php echo e(thai_date($row['submitted_at'])); ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
