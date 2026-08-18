<?php
/**
 * Executive dashboard.
 *
 * @var array $summary
 * @var array $departments  breakdown rows
 * @var array $school
 * @var int $year           survey year
 * @var int $gradYear       selected graduation year (0 = all)
 * @var array $gradYears
 */
$total = (int) $summary['total'];
$answered = (int) $summary['updated'];
$employedPct = $answered > 0 ? round(($summary['employed'] / $answered) * 100) : 0;
$studyPct = $answered > 0 ? round(($summary['study'] / $answered) * 100) : 0;
$placedPct = $answered > 0 ? round(($summary['placed'] / $answered) * 100) : 0;
$donutStop = min(100, $employedPct + $studyPct);
?>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
  <div>
    <h1 class="page-title">แดชบอร์ดผู้บริหาร</h1>
    <p class="page-sub">
      ภาพรวมภาวะการมีงานทำของศิษย์เก่า · <?php echo e($school['name']); ?> · ปีสำรวจ <?php echo e($year); ?>
    </p>
  </div>
  <form method="get" action="<?php echo e(url()); ?>" style="display:flex;gap:8px;align-items:center">
    <input type="hidden" name="r" value="exec">
    <label class="label" for="grad_year" style="margin:0">ปีที่สำเร็จการศึกษา</label>
    <select class="input input-sm" id="grad_year" name="grad_year" data-auto-submit style="width:150px">
      <option value="0">ทุกปี</option>
      <?php foreach ($gradYears as $y): ?>
        <option value="<?php echo e($y); ?>" <?php echo $gradYear === $y ? 'selected' : ''; ?>>
          <?php echo e($y); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="grid-4" style="margin-bottom:20px">
  <div class="card card-sm">
    <div class="kpi-label">ศิษย์เก่าทั้งหมด</div>
    <div class="kpi-value"><?php echo e(num($total)); ?></div>
    <div class="kpi-sub"><?php echo $gradYear > 0 ? 'ปีการศึกษา ' . e($gradYear) : 'ทุกปีการศึกษา'; ?></div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">อัปเดตข้อมูลแล้ว</div>
    <div class="kpi-value"><?php echo e(num($answered)); ?></div>
    <div class="kpi-sub"><?php echo e(pct($answered, $total)); ?>% ของทั้งหมด</div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">มีงานทำ</div>
    <div class="kpi-value"><?php echo e(num($summary['employed'])); ?></div>
    <div class="kpi-sub"><?php echo e($employedPct); ?>% ของผู้ตอบ</div>
  </div>
  <div class="card card-sm">
    <div class="kpi-label">ศึกษาต่อ</div>
    <div class="kpi-value"><?php echo e(num($summary['study'])); ?></div>
    <div class="kpi-sub"><?php echo e($studyPct); ?>% ของผู้ตอบ</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;margin-bottom:20px" class="dash-split">
  <div class="card" style="border-radius:16px;padding:24px">
    <h3 style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:20px">สถานะจำแนกตามแผนก</h3>
    <?php if (!$departments): ?>
      <p class="cell-dim">ยังไม่มีข้อมูลสาขาวิชา — เพิ่มได้ที่เมนูจัดการสาขาของผู้ดูแลสถานศึกษา</p>
    <?php else: ?>
      <?php foreach ($departments as $dept): ?>
        <div class="bar-row">
          <div class="bar-head">
            <span style="color:var(--text)"><?php echo e($dept['name']); ?></span>
            <span style="color:var(--text-dim)">
              <?php echo e($dept['pct']); ?>%
              <span style="opacity:.7">(<?php echo e($dept['placed']); ?>/<?php echo e($dept['answered']); ?>)</span>
            </span>
          </div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?php echo e((int) $dept['pct']); ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card" style="border-radius:16px;padding:24px;display:flex;flex-direction:column;align-items:center">
    <h3 style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:20px;align-self:flex-start">
      สัดส่วนสถานะรวม
    </h3>
    <div class="donut" style="background:conic-gradient(var(--primary) 0 <?php echo e($employedPct); ?>%, var(--primary-2) <?php echo e($employedPct); ?>% <?php echo e($donutStop); ?>%, var(--surface-2) <?php echo e($donutStop); ?>% 100%)">
      <div class="donut-hole">
        <span class="donut-pct"><?php echo e($placedPct); ?>%</span>
        <span class="donut-cap">มีงาน+ศึกษาต่อ</span>
      </div>
    </div>
    <div class="legend">
      <div class="legend-row">
        <span class="legend-chip" style="background:var(--primary)"></span>
        <span style="color:var(--text)">มีงานทำ</span>
        <span style="margin-left:auto;color:var(--text-dim)"><?php echo e($employedPct); ?>%</span>
      </div>
      <div class="legend-row">
        <span class="legend-chip" style="background:var(--primary-2)"></span>
        <span style="color:var(--text)">ศึกษาต่อ</span>
        <span style="margin-left:auto;color:var(--text-dim)"><?php echo e($studyPct); ?>%</span>
      </div>
      <div class="legend-row">
        <span class="legend-chip" style="background:var(--surface-2);border:1px solid var(--border)"></span>
        <span style="color:var(--text)">ว่างงาน/อื่นๆ</span>
        <span style="margin-left:auto;color:var(--text-dim)"><?php echo e(max(0, 100 - $donutStop)); ?>%</span>
      </div>
    </div>
  </div>
</div>

<div class="card" style="border-radius:16px;padding:22px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
  <div>
    <div style="font-weight:700;color:var(--text)">ความคืบหน้าการเก็บข้อมูล</div>
    <div style="font-size:13px;color:var(--text-dim);margin-top:4px">
      อัปเดตแล้ว <?php echo e(num($answered)); ?> จาก <?php echo e(num($total)); ?> คน
    </div>
  </div>
  <div style="text-align:right">
    <div style="font-size:28px;font-weight:700;color:var(--primary)"><?php echo e(pct($answered, $total)); ?>%</div>
  </div>
</div>
