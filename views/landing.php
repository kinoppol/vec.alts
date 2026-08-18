<?php
/**
 * Public landing page.
 *
 * @var array $summary  system-wide counters
 * @var array $chart    four bars for the hero card
 */
$features = array(
    array('icon' => '📝', 'title' => 'ศิษย์เก่ากรอกเอง',
        'desc' => 'เข้าระบบด้วยรหัสนักศึกษาเดิมและเลขบัตรประชาชน อัปเดตข้อมูลและสถานะปัจจุบันได้ทันที'),
    array('icon' => '👩‍🏫', 'title' => 'ครูกรอกแทนได้',
        'desc' => 'ครูที่ปรึกษากรอกข้อมูลแทนศิษย์เก่าที่ติดต่อได้ ลดปัญหาข้อมูลตกหล่น'),
    array('icon' => '📊', 'title' => 'แดชบอร์ดผู้บริหาร',
        'desc' => 'ผู้บริหารเห็นภาพรวมภาวะการมีงานทำ แยกตามแผนกและปีการศึกษา'),
    array('icon' => '🏫', 'title' => 'รองรับหลายสถานศึกษา',
        'desc' => 'แต่ละสถานศึกษาสมัครและบริหารข้อมูลของตนเองแยกกันอย่างปลอดภัย'),
    array('icon' => '🔐', 'title' => 'สิทธิ์ตามบทบาท',
        'desc' => 'กำหนดสิทธิ์ผู้ใช้งานตามบทบาท ตั้งแต่ศิษย์เก่าถึงผู้ดูแลระบบกลาง'),
    array('icon' => '📥', 'title' => 'นำเข้า-ส่งออกง่าย',
        'desc' => 'นำเข้าข้อมูลศิษย์เก่าจาก CSV และส่งออกรายงานเพื่อรายงานต้นสังกัด'),
);

$stats = array(
    array('value' => num($summary['active_schools']), 'label' => 'สถานศึกษาที่ใช้งาน'),
    array('value' => num($summary['alumni']), 'label' => 'ศิษย์เก่าในระบบ'),
    array('value' => $summary['placed_pct'] . '%', 'label' => 'อัตราการมีงานทำ/ศึกษาต่อ'),
    array('value' => '6', 'label' => 'สถานะที่ติดตามได้'),
);
?>

<section class="hero" id="overview">
  <div>
    <div class="pill"><span class="dot"></span>สำหรับสถานศึกษาสายอาชีวศึกษา</div>
    <h1>ติดตามเส้นทางของ<br><span class="grad-text">ผู้สำเร็จการศึกษา</span> อย่างเป็นระบบ</h1>
    <p>แพลตฟอร์มกลางให้สถานศึกษาหลายแห่งติดตามภาวะการมีงานทำและการศึกษาต่อของศิษย์เก่า
       พร้อมแดชบอร์ดภาพรวมสำหรับผู้บริหาร</p>
    <div class="hero-actions">
      <a class="btn btn-primary btn-lg" href="<?php echo e(url('login')); ?>">เข้าสู่ระบบสำหรับศิษย์เก่า</a>
      <a class="btn btn-lg" href="<?php echo e(url('register')); ?>">สมัครสถานศึกษา →</a>
    </div>
    <?php if ($summary['active_schools'] > 0): ?>
      <div class="hero-note">
        ปีการศึกษาที่กำลังสำรวจ · <b style="color:var(--text)"><?php echo e($summary['survey_year']); ?></b>
      </div>
    <?php endif; ?>
  </div>

  <div class="hero-card">
    <div class="hero-card-title">ภาวะการมีงานทำ · ปีการศึกษา <?php echo e($summary['survey_year']); ?></div>
    <div class="minichart">
      <?php foreach ($chart as $bar): ?>
        <div class="col">
          <div class="bar<?php echo e($bar['class']); ?>"
               style="height:<?php echo e(max(2, (int) $bar['height'])); ?>%"
               title="<?php echo e($bar['label'] . ' ' . $bar['count'] . ' คน'); ?>"></div>
          <span class="lbl"><?php echo e($bar['label']); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="hero-card-foot">
      <span style="font-size:13px;color:var(--text-dim)">อัตราการมีงานทำ/ศึกษาต่อ</span>
      <span style="font-size:18px;font-weight:700;color:var(--primary)"><?php echo e($summary['placed_pct']); ?>%</span>
    </div>
  </div>
</section>

<section class="wrap" style="padding-top:8px;padding-bottom:56px">
  <div class="grid-4" style="gap:18px">
    <?php foreach ($stats as $stat): ?>
      <div class="card">
        <div class="stat-value"><?php echo e($stat['value']); ?></div>
        <div class="stat-label"><?php echo e($stat['label']); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="wrap" id="benefits" style="padding-top:8px;padding-bottom:72px">
  <h2 class="section-title">ทำไมต้องใช้ระบบนี้</h2>
  <p class="section-sub">ออกแบบมาเพื่อลดภาระงานเอกสารและได้ข้อมูลที่ใช้ตัดสินใจได้จริง</p>
  <div class="grid-3" style="gap:18px">
    <?php foreach ($features as $feature): ?>
      <div class="card" style="padding:26px">
        <div class="feature-icon"><?php echo e($feature['icon']); ?></div>
        <div class="feature-title"><?php echo e($feature['title']); ?></div>
        <div class="feature-desc"><?php echo e($feature['desc']); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="wrap" id="users" style="padding-top:8px;padding-bottom:72px">
  <h2 class="section-title">ผู้ใช้งานในระบบ</h2>
  <p class="section-sub">แต่ละบทบาทเห็นเฉพาะข้อมูลและเครื่องมือที่เกี่ยวข้องกับหน้าที่ของตนเอง</p>
  <div class="grid-3" style="gap:18px">
    <?php
    $roles = array(
        array('ศิษย์เก่า', 'เข้าระบบด้วยรหัสนักศึกษาและเลขบัตรประชาชน กรอกสถานะการทำงานหรือการศึกษาต่อของตนเอง'),
        array('ครูที่ปรึกษา', 'ดูรายชื่อศิษย์เก่าในความดูแล ติดตามผู้ที่ยังไม่อัปเดต และกรอกข้อมูลแทนได้'),
        array('ผู้บริหาร', 'ดูแดชบอร์ดภาวะการมีงานทำ แยกตามแผนก และเปรียบเทียบระหว่างปีการศึกษา'),
        array('ผู้ดูแลสถานศึกษา', 'จัดการผู้ใช้งาน สาขาวิชา และนำเข้าข้อมูลศิษย์เก่าของสถานศึกษาตนเอง'),
        array('ผู้ดูแลระบบกลาง', 'อนุมัติสถานศึกษาที่สมัครเข้าใช้งาน ดูแลผู้ใช้ทั้งระบบ และจัดการโครงสร้างฐานข้อมูล'),
        array('รายงานต้นสังกัด', 'ส่งออกข้อมูลเป็นไฟล์ CSV เพื่อนำไปใช้รายงานหน่วยงานต้นสังกัดได้ทันที'),
    );
    foreach ($roles as $role): ?>
      <div class="card" style="padding:26px">
        <div class="feature-title"><?php echo e($role[0]); ?></div>
        <div class="feature-desc"><?php echo e($role[1]); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
