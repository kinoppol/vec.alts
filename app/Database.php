<?php
/**
 * PDO connection factory + server capability detection.
 *
 * Deliberately tolerant: MySQL 5.5/5.6/5.7, MySQL 8, MariaDB 10.x all differ
 * in charset support, default sql_mode and reserved words. The capabilities
 * detected here are what the migrations build their DDL from.
 */
class Database
{
    /** @var PDO|null */
    private static $pdo = null;

    /** @var array */
    private static $caps = array();

    /**
     * @param array $cfg host, port, name, user, pass, socket (optional)
     * @return PDO
     */
    public static function connect($cfg, $withDatabase = true)
    {
        $host   = isset($cfg['host']) ? $cfg['host'] : 'localhost';
        $port   = isset($cfg['port']) && $cfg['port'] !== '' ? (int) $cfg['port'] : 3306;
        $name   = isset($cfg['name']) ? $cfg['name'] : '';
        $user   = isset($cfg['user']) ? $cfg['user'] : 'root';
        $pass   = isset($cfg['pass']) ? $cfg['pass'] : '';
        $socket = isset($cfg['socket']) ? $cfg['socket'] : '';

        if ($socket !== '') {
            $dsn = 'mysql:unix_socket=' . $socket;
        } else {
            $dsn = 'mysql:host=' . $host . ';port=' . $port;
        }
        if ($withDatabase && $name !== '') {
            $dsn .= ';dbname=' . $name;
        }

        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        );
        // PHP 5.4 + old libmysql needs this constant guarded.
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }

        $pdo = new PDO($dsn, $user, $pass, $options);

        self::applySessionSettings($pdo);

        return $pdo;
    }

    /**
     * Normalises the session so behaviour matches across MySQL 5.5 (loose by
     * default) and MySQL 5.7+/MariaDB 10.2+ (STRICT + ONLY_FULL_GROUP_BY).
     */
    private static function applySessionSettings(PDO $pdo)
    {
        $charset = self::detectCharset($pdo);
        try {
            $pdo->exec("SET NAMES '" . $charset . "'");
        } catch (PDOException $e) {
            try {
                $pdo->exec("SET NAMES 'utf8'");
            } catch (PDOException $e2) {
                // keep the server default
            }
        }
        // NO_ENGINE_SUBSTITUTION exists on 5.5.3+ and every MariaDB 10.
        try {
            $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            try {
                $pdo->exec("SET SESSION sql_mode = ''");
            } catch (PDOException $e2) {
                // server does not allow changing it; carry on
            }
        }
        try {
            $pdo->exec("SET SESSION time_zone = '+07:00'");
        } catch (PDOException $e) {
            // tz tables may not be loaded; app stores plain DATETIME anyway
        }
    }

    /**
     * utf8mb4 where available (emoji-safe), utf8 on ancient servers.
     * @return string
     */
    public static function detectCharset(PDO $pdo)
    {
        try {
            $stmt = $pdo->query("SHOW CHARACTER SET LIKE 'utf8mb4'");
            $row = $stmt->fetch();
            if ($row) {
                return 'utf8mb4';
            }
        } catch (PDOException $e) {
            // fall through
        }
        return 'utf8';
    }

    /**
     * @return array server flavour/version/charset/collation facts
     */
    public static function capabilities(PDO $pdo)
    {
        $key = spl_object_hash($pdo);
        if (isset(self::$caps[$key])) {
            return self::$caps[$key];
        }

        $versionString = '';
        try {
            $versionString = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (PDOException $e) {
            $versionString = '';
        }
        if ($versionString === '') {
            try {
                $row = $pdo->query('SELECT VERSION() AS v')->fetch();
                $versionString = isset($row['v']) ? (string) $row['v'] : '';
            } catch (PDOException $e) {
                $versionString = '0.0.0';
            }
        }

        $isMariaDb = stripos($versionString, 'mariadb') !== false;
        $numeric = '0.0.0';
        if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $versionString, $m)) {
            $numeric = $m[1] . '.' . $m[2] . '.' . $m[3];
        }

        $charset = self::detectCharset($pdo);
        $collation = ($charset === 'utf8mb4') ? 'utf8mb4_unicode_ci' : 'utf8_unicode_ci';

        // MySQL 8 dropped utf8mb4_unicode_ci as a default but still supports
        // it; verify rather than assume. Queried through information_schema
        // because SHOW COLLATION LIKE ? cannot take a placeholder under native
        // prepared statements.
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `information_schema`.`COLLATIONS`'
                . ' WHERE `COLLATION_NAME` = ?'
            );
            $stmt->execute(array($collation));
            $row = $stmt->fetch(PDO::FETCH_NUM);
            if ($row === false || (int) $row[0] === 0) {
                $collation = ($charset === 'utf8mb4') ? 'utf8mb4_general_ci' : 'utf8_general_ci';
            }
        } catch (PDOException $e) {
            // keep the guess
        }

        // Long index prefixes: safe on MariaDB 10.2+ / MySQL 5.7+, risky on 5.5.
        $supportsLongIndex = true;
        if (!$isMariaDb && version_compare($numeric, '5.7.0', '<')) {
            $supportsLongIndex = false;
        }

        $caps = array(
            'flavour'      => $isMariaDb ? 'MariaDB' : 'MySQL',
            'version'      => $numeric,
            'version_full' => $versionString,
            'charset'      => $charset,
            'collation'    => $collation,
            'engine'       => 'InnoDB',
            'long_index'   => $supportsLongIndex,
            // Keep indexed strings at 191 chars so utf8mb4 fits the old
            // 767-byte InnoDB prefix limit on MySQL 5.5/5.6.
            'index_len'    => ($charset === 'utf8mb4' && !$supportsLongIndex) ? 191 : 191,
        );

        self::$caps[$key] = $caps;
        return $caps;
    }

    public static function setShared(PDO $pdo)
    {
        self::$pdo = $pdo;
    }

    /**
     * @return PDO
     */
    public static function shared()
    {
        if (self::$pdo === null) {
            throw new RuntimeException('Database connection has not been initialised.');
        }
        return self::$pdo;
    }

    public static function hasShared()
    {
        return self::$pdo !== null;
    }
}
