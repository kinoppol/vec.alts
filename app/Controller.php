<?php
/**
 * Base for every controller: holds the shared services and a couple of
 * conveniences the subclasses all want.
 */
abstract class Controller
{
    /** @var Auth */
    protected $auth;

    /** @var Repository */
    protected $repo;

    /** @var View */
    protected $view;

    /** @var array */
    protected $config;

    /** @var string current route */
    protected $route;

    public function __construct(Auth $auth, Repository $repo, View $view, $config, $route)
    {
        $this->auth = $auth;
        $this->repo = $repo;
        $this->view = $view;
        $this->config = $config;
        $this->route = $route;
    }

    /**
     * Render inside the signed-in shell.
     * @param string $template
     * @param array $data
     */
    protected function render($template, $data = array())
    {
        $data['route'] = $this->route;
        $this->view->render($template, $data, 'layout/app');
    }

    /**
     * Render inside the public marketing chrome.
     */
    protected function renderPublic($template, $data = array())
    {
        $data['route'] = $this->route;
        $this->view->render($template, $data, 'layout/public');
    }

    /**
     * Render with no chrome at all.
     */
    protected function renderBlank($template, $data = array())
    {
        $data['route'] = $this->route;
        $this->view->render($template, $data, 'layout/blank');
    }

    /**
     * The signed-in user's institution, or null for the central admin.
     * @return array|null
     */
    protected function currentSchool()
    {
        $id = $this->auth->schoolId();
        if ($id === null) {
            return null;
        }
        return $this->repo->school($id);
    }

    /**
     * Session identity, for the audit log.
     * @return array|null
     */
    protected function actor()
    {
        return $this->auth->user();
    }

    /**
     * Runtime facts shown on the settings and migration screens.
     * @return array
     */
    protected function environment()
    {
        $pdo = $this->repo->db();
        $caps = Database::capabilities($pdo);
        $prefix = $this->config['db']['prefix'];

        $migrator = new Migrator($pdo, VEC_ROOT . '/migrations', $prefix);
        $applied = $migrator->applied();
        $latest = '—';
        if ($applied) {
            $versions = array_keys($applied);
            sort($versions, SORT_STRING);
            $last = end($versions);
            $latest = $last . ' · ' . $applied[$last]['name'];
        }

        $driver = 'unknown';
        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException $e) {
            // some drivers do not expose it; not worth failing over
        }

        return array(
            'php'         => PHP_VERSION,
            'db_flavour'  => $caps['flavour'],
            'db_version'  => $caps['version_full'],
            'charset'     => $caps['charset'],
            'collation'   => $caps['collation'],
            'driver'      => $driver,
            'prefix'      => $prefix !== '' ? $prefix : '(ไม่มี)',
            'timezone'    => date_default_timezone_get(),
            'migration'   => $latest,
            'batch'       => $migrator->currentBatch(),
            'app_version' => VEC_VERSION,
        );
    }

    /**
     * Streams an array of rows as a CSV download, BOM-prefixed so Excel on
     * Windows reads Thai text correctly.
     *
     * @param string $filename
     * @param array $header
     * @param array $rows
     */
    protected function csvDownload($filename, $header, $rows)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}
