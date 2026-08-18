<?php
/**
 * Small DDL helper handed to every migration.
 *
 * It hides the differences between MySQL 5.x, MySQL 8 and MariaDB 10 that
 * would otherwise leak into each migration: table prefixes, charset choice,
 * index-length limits and the fact that "ADD COLUMN IF NOT EXISTS" only
 * exists on MariaDB.
 */
class Schema
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $prefix;

    /** @var array */
    private $caps;

    /** @var array executed statements, for the migration log */
    private $log = array();

    public function __construct(PDO $db, $prefix, $caps)
    {
        $this->db = $db;
        $this->prefix = (string) $prefix;
        $this->caps = $caps;
    }

    /** @return PDO */
    public function db()
    {
        return $this->db;
    }

    /**
     * Prefixed table name.
     * @param string $name
     * @return string
     */
    public function table($name)
    {
        return $this->prefix . $name;
    }

    public function prefix()
    {
        return $this->prefix;
    }

    public function charset()
    {
        return $this->caps['charset'];
    }

    public function collation()
    {
        return $this->caps['collation'];
    }

    public function caps()
    {
        return $this->caps;
    }

    /** Max length for a VARCHAR that is going to be indexed. */
    public function indexLen()
    {
        return (int) $this->caps['index_len'];
    }

    /** Trailing clause for CREATE TABLE. */
    public function tableOptions()
    {
        return ' ENGINE=' . $this->caps['engine']
            . ' DEFAULT CHARSET=' . $this->caps['charset']
            . ' COLLATE=' . $this->caps['collation'];
    }

    /**
     * Run a statement. `{p}` in the SQL is replaced by the table prefix.
     * @param string $sql
     * @return int affected rows
     */
    public function exec($sql)
    {
        $sql = str_replace('{p}', $this->prefix, $sql);
        $this->log[] = $sql;
        return $this->db->exec($sql);
    }

    /**
     * Prepared statement with `{p}` prefix substitution.
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function run($sql, $params = array())
    {
        $sql = str_replace('{p}', $this->prefix, $sql);
        $this->log[] = $sql;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * CREATE TABLE IF NOT EXISTS with the right engine/charset appended.
     * @param string $name unprefixed
     * @param string $body column definitions, without the outer parentheses
     */
    public function createTable($name, $body)
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $this->table($name) . '` ('
            . $body . ')' . $this->tableOptions();
        return $this->exec($sql);
    }

    /**
     * @param string $name unprefixed
     */
    public function dropTable($name)
    {
        return $this->exec('DROP TABLE IF EXISTS `' . $this->table($name) . '`');
    }

    /**
     * Existence checks go through information_schema rather than SHOW.
     *
     * Neither MySQL nor MariaDB accepts a placeholder in `SHOW ... LIKE ?`
     * over the binary protocol, and this connection uses native prepared
     * statements — so a SHOW-based check would raise a syntax error and, if
     * that error were swallowed, quietly report "does not exist" for
     * everything. information_schema takes parameters normally and is
     * available on every supported server.
     *
     * @param string $table unprefixed
     * @return bool
     */
    public function hasTable($table)
    {
        $count = $this->countFromInformationSchema(
            'SELECT COUNT(*) FROM `information_schema`.`TABLES`'
            . ' WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?',
            array($this->table($table))
        );
        return $count > 0;
    }

    /**
     * @param string $table unprefixed
     * @param string $column
     * @return bool
     */
    public function hasColumn($table, $column)
    {
        $count = $this->countFromInformationSchema(
            'SELECT COUNT(*) FROM `information_schema`.`COLUMNS`'
            . ' WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `COLUMN_NAME` = ?',
            array($this->table($table), $column)
        );
        return $count > 0;
    }

    /**
     * @param string $table unprefixed
     * @param string $index
     * @return bool
     */
    public function hasIndex($table, $index)
    {
        $count = $this->countFromInformationSchema(
            'SELECT COUNT(*) FROM `information_schema`.`STATISTICS`'
            . ' WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `INDEX_NAME` = ?',
            array($this->table($table), $index)
        );
        return $count > 0;
    }

    /**
     * @param string $sql
     * @param array $params
     * @return int
     */
    private function countFromInformationSchema($sql, $params)
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row === false ? 0 : (int) $row[0];
    }

    /**
     * Idempotent ADD COLUMN. Portable across MySQL (no IF NOT EXISTS) and
     * MariaDB by checking first instead of relying on syntax.
     */
    public function addColumn($table, $column, $definition, $after = null)
    {
        if ($this->hasColumn($table, $column)) {
            return false;
        }
        $sql = 'ALTER TABLE `' . $this->table($table) . '` ADD COLUMN `'
            . $column . '` ' . $definition;
        if ($after !== null) {
            $sql .= ' AFTER `' . $after . '`';
        }
        $this->exec($sql);
        return true;
    }

    public function dropColumn($table, $column)
    {
        if (!$this->hasColumn($table, $column)) {
            return false;
        }
        $this->exec('ALTER TABLE `' . $this->table($table) . '` DROP COLUMN `' . $column . '`');
        return true;
    }

    public function modifyColumn($table, $column, $definition)
    {
        if (!$this->hasColumn($table, $column)) {
            return false;
        }
        $this->exec('ALTER TABLE `' . $this->table($table) . '` MODIFY `'
            . $column . '` ' . $definition);
        return true;
    }

    /**
     * @param string $table unprefixed
     * @param string $name index name
     * @param string $columns e.g. "`school_id`, `student_code`"
     * @param bool $unique
     */
    public function addIndex($table, $name, $columns, $unique = false)
    {
        if ($this->hasIndex($table, $name)) {
            return false;
        }
        $sql = 'ALTER TABLE `' . $this->table($table) . '` ADD '
            . ($unique ? 'UNIQUE ' : '') . 'INDEX `' . $name . '` (' . $columns . ')';
        $this->exec($sql);
        return true;
    }

    public function dropIndex($table, $name)
    {
        if (!$this->hasIndex($table, $name)) {
            return false;
        }
        $this->exec('ALTER TABLE `' . $this->table($table) . '` DROP INDEX `' . $name . '`');
        return true;
    }

    /**
     * Statements executed so far, for display in the Migration admin screen.
     * @return array
     */
    public function statements()
    {
        return $this->log;
    }

    public function clearLog()
    {
        $this->log = array();
    }
}
