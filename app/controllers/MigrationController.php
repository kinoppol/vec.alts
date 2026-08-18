<?php
/**
 * The Migration screen: apply pending schema changes, roll the last batch
 * back, or run one migration at a time.
 *
 * Restricted to the central administrator, since it changes the shape of the
 * database for every institution at once.
 */
class MigrationController extends Controller
{
    public function index()
    {
        $this->auth->require_role('centraladmin');

        $migrator = new Migrator(
            $this->repo->db(),
            VEC_ROOT . '/migrations',
            $this->config['db']['prefix']
        );

        $lastResult = null;

        if (is_post()) {
            csrf_verify();
            $action = post('action');

            if ($action === 'migrate') {
                $lastResult = $this->applyPending($migrator);
            } elseif ($action === 'rollback') {
                $lastResult = $this->rollbackLast($migrator);
            } elseif ($action === 'migrate-one') {
                $lastResult = $this->applyOne($migrator, post('version'));
            } else {
                flash('error', 'คำสั่งไม่ถูกต้อง');
            }
        }

        $rows = $migrator->status();
        $pendingCount = 0;
        foreach ($rows as $row) {
            if ($row['state'] === 'pending') {
                $pendingCount++;
            }
        }

        $this->render('admin/migrations', array(
            'title'        => 'Migration ฐานข้อมูล',
            'rows'         => $rows,
            'pendingCount' => $pendingCount,
            'env'          => $this->environment(),
            'lastResult'   => $lastResult,
        ));
    }

    /**
     * @return array
     */
    private function applyPending(Migrator $migrator)
    {
        $result = $migrator->migrate();

        $count = count($result['applied']);
        if ($count > 0) {
            flash('success', 'ปรับปรุงฐานข้อมูลสำเร็จ ' . $count . ' รายการ (batch ' . $result['batch'] . ')');
            $this->repo->audit(
                'migration.up',
                'batch ' . $result['batch'],
                implode(', ', array_column($result['applied'], 'version')),
                $this->actor()
            );
        }
        if ($result['failed'] !== null) {
            flash('error', 'migration ' . $result['failed']['version'] . ' ล้มเหลว: '
                . $result['failed']['error']);
            app_log('migration failed: ' . $result['failed']['version']
                . ' — ' . $result['failed']['error']);
        } elseif ($count === 0) {
            flash('info', 'ไม่มี migration ที่ต้องปรับปรุง');
        }

        return array('sql' => $result['sql']);
    }

    /**
     * @return array
     */
    private function rollbackLast(Migrator $migrator)
    {
        $result = $migrator->rollback();

        $count = count($result['rolled_back']);
        if ($count > 0) {
            flash('success', 'ย้อนกลับสำเร็จ ' . $count . ' รายการ (batch ' . $result['batch'] . ')');
            $this->repo->audit(
                'migration.down',
                'batch ' . $result['batch'],
                implode(', ', array_column($result['rolled_back'], 'version')),
                $this->actor()
            );
        }
        if ($result['failed'] !== null) {
            flash('error', 'ย้อนกลับ ' . $result['failed']['version'] . ' ล้มเหลว: '
                . $result['failed']['error']);
        } elseif ($count === 0) {
            flash('info', 'ไม่มี migration ที่ย้อนกลับได้');
        }

        return array('sql' => $result['sql']);
    }

    /**
     * @param string $version
     * @return array
     */
    private function applyOne(Migrator $migrator, $version)
    {
        if ($version === '') {
            flash('error', 'ไม่ได้ระบุเวอร์ชันที่ต้องการรัน');
            return array('sql' => array());
        }

        $result = $migrator->migrateOne($version);
        if (!empty($result['ok'])) {
            flash('success', 'รัน migration ' . $version . ' สำเร็จ (' . $result['ms'] . ' ms)');
            $this->repo->audit('migration.up.one', $version, $result['name'], $this->actor());
        } else {
            flash('error', 'รัน migration ' . $version . ' ไม่สำเร็จ: ' . $result['error']);
        }

        return array('sql' => $result['sql']);
    }
}
