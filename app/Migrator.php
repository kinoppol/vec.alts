<?php
/**
 * File-based migration runner.
 *
 * Each file in migrations/ is named NNNN_description.php and returns:
 *   array('name' => '...', 'up' => function (Schema $s) {}, 'down' => function (Schema $s) {})
 *
 * Applied migrations are recorded in the `<prefix>migrations` table together
 * with a batch number, so the whole of the last batch can be rolled back.
 *
 * DDL is not transactional in MySQL, so each migration is committed on its own
 * and a failure stops the run with the partially-applied migration reported.
 */
class Migrator
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $dir;

    /** @var Schema */
    private $schema;

    /** @var string */
    private $prefix;

    public function __construct(PDO $db, $migrationsDir, $prefix)
    {
        $this->db = $db;
        $this->dir = rtrim($migrationsDir, "/\\");
        $this->prefix = (string) $prefix;
        $this->schema = new Schema($db, $prefix, Database::capabilities($db));
    }

    /** @return Schema */
    public function schema()
    {
        return $this->schema;
    }

    public function table()
    {
        return $this->prefix . 'migrations';
    }

    /**
     * Creates the bookkeeping table. Safe to call repeatedly.
     */
    public function ensureRepository()
    {
        $caps = Database::capabilities($this->db);
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $this->table() . '` ('
            . '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`version` VARCHAR(64) NOT NULL,'
            . '`name` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`batch` INT UNSIGNED NOT NULL DEFAULT 1,'
            . '`applied_at` DATETIME NULL DEFAULT NULL,'
            . '`runtime_ms` INT UNSIGNED NOT NULL DEFAULT 0,'
            . 'PRIMARY KEY (`id`),'
            . 'UNIQUE KEY `uq_version` (`version`)'
            . ') ENGINE=' . $caps['engine']
            . ' DEFAULT CHARSET=' . $caps['charset']
            . ' COLLATE=' . $caps['collation'];
        $this->db->exec($sql);
    }

    public function repositoryExists()
    {
        // information_schema rather than SHOW TABLES LIKE ?, which cannot take
        // a placeholder under native prepared statements. See Schema::hasTable.
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM `information_schema`.`TABLES`'
                . ' WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?'
            );
            $stmt->execute(array($this->table()));
            $row = $stmt->fetch(PDO::FETCH_NUM);
            return $row !== false && (int) $row[0] > 0;
        } catch (PDOException $e) {
            app_log('repositoryExists check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * All migration files on disk, keyed by version, sorted ascending.
     * @return array version => array('version','name','file')
     */
    public function available()
    {
        $out = array();
        if (!is_dir($this->dir)) {
            return $out;
        }
        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.php');
        if (!is_array($files)) {
            return $out;
        }
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $base = basename($file, '.php');
            $parts = explode('_', $base, 2);
            $version = $parts[0];
            $label = isset($parts[1]) ? str_replace('_', ' ', $parts[1]) : $base;

            // Prefer the human-readable name the migration declares, so the
            // admin screen shows the same label before and after it is run.
            try {
                $definition = include $file;
                if (is_array($definition) && isset($definition['name']) && $definition['name'] !== '') {
                    $label = (string) $definition['name'];
                }
            } catch (Exception $e) {
                // A broken file still deserves a row in the listing; the run
                // itself will report the real error.
            }

            $out[$version] = array(
                'version' => $version,
                'name'    => $label,
                'file'    => $file,
            );
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Highest version present on disk, read from filenames only.
     *
     * Deliberately does not include the migration files the way available()
     * does: this runs on every request, so it must stay to one directory scan.
     *
     * @return string '' when the directory is empty
     */
    public function latestAvailableVersion()
    {
        if (!is_dir($this->dir)) {
            return '';
        }
        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.php');
        if (!is_array($files) || !$files) {
            return '';
        }
        $latest = '';
        foreach ($files as $file) {
            $parts = explode('_', basename($file, '.php'), 2);
            if ($parts[0] !== '' && strcmp($parts[0], $latest) > 0) {
                $latest = $parts[0];
            }
        }
        return $latest;
    }

    /**
     * Highest version recorded as applied.
     *
     * @return string '' when nothing has been applied, or the bookkeeping
     *                table does not exist yet
     */
    public function latestAppliedVersion()
    {
        try {
            $row = $this->db->query(
                'SELECT MAX(`version`) FROM `' . $this->table() . '`'
            )->fetch(PDO::FETCH_NUM);
        } catch (PDOException $e) {
            // No migrations table means nothing has ever been applied.
            return '';
        }
        return ($row === false || $row[0] === null) ? '' : (string) $row[0];
    }

    /**
     * Whether the database is behind the code.
     *
     * Costs one directory scan and one indexed query, so it is cheap enough
     * to check on every request. Deploying new code without running the
     * migrations would otherwise surface as raw SQL errors on public pages.
     *
     * @return bool
     */
    public function isOutdated()
    {
        $available = $this->latestAvailableVersion();
        if ($available === '') {
            return false;
        }
        return strcmp($available, $this->latestAppliedVersion()) > 0;
    }

    /**
     * @return array version => row
     */
    public function applied()
    {
        if (!$this->repositoryExists()) {
            return array();
        }
        $rows = $this->db->query(
            'SELECT * FROM `' . $this->table() . '` ORDER BY `version` ASC'
        )->fetchAll();
        $out = array();
        foreach ($rows as $row) {
            $out[$row['version']] = $row;
        }
        return $out;
    }

    /**
     * @return array version => file info, only those not yet applied
     */
    public function pending()
    {
        $applied = $this->applied();
        $out = array();
        foreach ($this->available() as $version => $info) {
            if (!isset($applied[$version])) {
                $out[$version] = $info;
            }
        }
        return $out;
    }

    /**
     * Combined view for the admin screen.
     * @return array
     */
    public function status()
    {
        $applied = $this->applied();
        $available = $this->available();
        $rows = array();

        foreach ($available as $version => $info) {
            $rows[$version] = array(
                'version'    => $version,
                'name'       => $info['name'],
                'file'       => basename($info['file']),
                'state'      => isset($applied[$version]) ? 'applied' : 'pending',
                'batch'      => isset($applied[$version]) ? (int) $applied[$version]['batch'] : null,
                'applied_at' => isset($applied[$version]) ? $applied[$version]['applied_at'] : null,
                'runtime_ms' => isset($applied[$version]) ? (int) $applied[$version]['runtime_ms'] : null,
            );
        }
        // Recorded in the DB but the file is gone: worth flagging, not hiding.
        foreach ($applied as $version => $row) {
            if (!isset($available[$version])) {
                $rows[$version] = array(
                    'version'    => $version,
                    'name'       => $row['name'],
                    'file'       => '(missing)',
                    'state'      => 'missing',
                    'batch'      => (int) $row['batch'],
                    'applied_at' => $row['applied_at'],
                    'runtime_ms' => (int) $row['runtime_ms'],
                );
            }
        }
        ksort($rows, SORT_STRING);
        return array_values($rows);
    }

    public function currentBatch()
    {
        if (!$this->repositoryExists()) {
            return 0;
        }
        $row = $this->db->query('SELECT MAX(`batch`) AS b FROM `' . $this->table() . '`')->fetch();
        return $row && $row['b'] !== null ? (int) $row['b'] : 0;
    }

    /**
     * Loads a migration file and validates its shape.
     * @return array
     */
    private function load($file)
    {
        $migration = include $file;
        if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
            throw new RuntimeException(
                'Migration ' . basename($file) . ' must return an array with a callable "up".'
            );
        }
        return $migration;
    }

    /**
     * Applies every pending migration.
     *
     * @return array array('applied' => [...], 'failed' => null|array, 'sql' => [...])
     */
    public function migrate()
    {
        $this->ensureRepository();
        $pending = $this->pending();
        $batch = $this->currentBatch() + 1;

        $result = array('applied' => array(), 'failed' => null, 'sql' => array(), 'batch' => $batch);
        if (!$pending) {
            return $result;
        }

        $insert = $this->db->prepare(
            'INSERT INTO `' . $this->table() . '` (`version`, `name`, `batch`, `applied_at`, `runtime_ms`)'
            . ' VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($pending as $version => $info) {
            $this->schema->clearLog();
            $start = microtime(true);
            try {
                $migration = $this->load($info['file']);
                $name = isset($migration['name']) ? $migration['name'] : $info['name'];
                call_user_func($migration['up'], $this->schema);
                $ms = (int) round((microtime(true) - $start) * 1000);
                $insert->execute(array($version, $name, $batch, date('Y-m-d H:i:s'), $ms));
                $result['applied'][] = array(
                    'version' => $version,
                    'name'    => $name,
                    'ms'      => $ms,
                );
                $result['sql'] = array_merge($result['sql'], $this->schema->statements());
            } catch (Exception $e) {
                $result['sql'] = array_merge($result['sql'], $this->schema->statements());
                $result['failed'] = array(
                    'version' => $version,
                    'name'    => $info['name'],
                    'error'   => $e->getMessage(),
                );
                break;
            }
        }
        return $result;
    }

    /**
     * Rolls back every migration in the most recent batch, newest first.
     *
     * @return array
     */
    public function rollback()
    {
        $result = array('rolled_back' => array(), 'failed' => null, 'sql' => array(), 'batch' => 0);
        if (!$this->repositoryExists()) {
            return $result;
        }
        $batch = $this->currentBatch();
        if ($batch < 1) {
            return $result;
        }
        $result['batch'] = $batch;

        $stmt = $this->db->prepare(
            'SELECT * FROM `' . $this->table() . '` WHERE `batch` = ? ORDER BY `version` DESC'
        );
        $stmt->execute(array($batch));
        $rows = $stmt->fetchAll();

        $available = $this->available();
        $delete = $this->db->prepare('DELETE FROM `' . $this->table() . '` WHERE `version` = ?');

        foreach ($rows as $row) {
            $version = $row['version'];
            $this->schema->clearLog();
            try {
                if (!isset($available[$version])) {
                    throw new RuntimeException(
                        'Migration file for version ' . $version . ' is missing; cannot roll back.'
                    );
                }
                $migration = $this->load($available[$version]['file']);
                if (isset($migration['down']) && is_callable($migration['down'])) {
                    call_user_func($migration['down'], $this->schema);
                }
                $delete->execute(array($version));
                $result['rolled_back'][] = array('version' => $version, 'name' => $row['name']);
                $result['sql'] = array_merge($result['sql'], $this->schema->statements());
            } catch (Exception $e) {
                $result['sql'] = array_merge($result['sql'], $this->schema->statements());
                $result['failed'] = array(
                    'version' => $version,
                    'name'    => $row['name'],
                    'error'   => $e->getMessage(),
                );
                break;
            }
        }
        return $result;
    }

    /**
     * Applies a single migration by version, regardless of batch ordering.
     * Used by the admin screen for re-running one step.
     * @return array
     */
    public function migrateOne($version)
    {
        $this->ensureRepository();
        $available = $this->available();
        if (!isset($available[$version])) {
            return array('ok' => false, 'error' => 'ไม่พบไฟล์ migration เวอร์ชัน ' . $version, 'sql' => array());
        }
        $applied = $this->applied();
        if (isset($applied[$version])) {
            return array('ok' => false, 'error' => 'migration นี้ถูกใช้งานไปแล้ว', 'sql' => array());
        }

        $this->schema->clearLog();
        $batch = $this->currentBatch() + 1;
        $start = microtime(true);
        try {
            $migration = $this->load($available[$version]['file']);
            $name = isset($migration['name']) ? $migration['name'] : $available[$version]['name'];
            call_user_func($migration['up'], $this->schema);
            $ms = (int) round((microtime(true) - $start) * 1000);
            $stmt = $this->db->prepare(
                'INSERT INTO `' . $this->table() . '` (`version`, `name`, `batch`, `applied_at`, `runtime_ms`)'
                . ' VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute(array($version, $name, $batch, date('Y-m-d H:i:s'), $ms));
            return array('ok' => true, 'version' => $version, 'name' => $name,
                'ms' => $ms, 'sql' => $this->schema->statements());
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage(),
                'sql' => $this->schema->statements());
        }
    }

    /**
     * Tables this application owns, unprefixed. Used when no table prefix is
     * configured, so a clean reinstall cannot touch unrelated tables that
     * happen to share the database.
     * @return array
     */
    public static function ownedTables()
    {
        return array(
            'migrations', 'audit_log', 'settings', 'alumni_status',
            'alumni', 'users', 'departments', 'schools',
        );
    }

    /**
     * Drops every table this application owns. Used only by the installer's
     * explicit "clean reinstall" path.
     *
     * With a prefix configured, every prefixed table goes. Without one, only
     * the known table names are dropped — never everything in the schema.
     *
     * @return array dropped table names
     */
    public function dropAllTables()
    {
        $prefix = $this->prefix;
        $targets = array();

        if ($prefix !== '') {
            $rows = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
            foreach ($rows as $row) {
                if (strpos($row[0], $prefix) === 0) {
                    $targets[] = $row[0];
                }
            }
        } else {
            foreach (self::ownedTables() as $table) {
                $targets[] = $table;
            }
        }

        $dropped = array();
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($targets as $table) {
            $this->db->exec('DROP TABLE IF EXISTS `' . $table . '`');
            $dropped[] = $table;
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
        return $dropped;
    }
}
