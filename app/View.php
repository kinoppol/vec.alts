<?php
/**
 * Plain PHP templating: render a view into a layout. No engine, no compile
 * step, nothing that needs a writable cache directory on the production box.
 */
class View
{
    /** @var string */
    private $path;

    /** @var array data shared with every view */
    private $shared = array();

    public function __construct($viewPath)
    {
        $this->path = rtrim($viewPath, "/\\");
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function share($key, $value)
    {
        $this->shared[$key] = $value;
    }

    /**
     * Render a view without a layout and return the markup.
     * @param string $view dotted or slashed path, e.g. 'alumni/form'
     * @param array $data
     * @return string
     */
    public function partial($view, $data = array())
    {
        $file = $this->path . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $view);
        }
        $vars = array_merge($this->shared, $data);
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Render a view inside a layout and echo the result.
     * @param string $view
     * @param array $data
     * @param string $layout
     */
    public function render($view, $data = array(), $layout = 'layout/app')
    {
        $content = $this->partial($view, $data);
        if ($layout === null || $layout === '') {
            echo $content;
            return;
        }
        $data['content'] = $content;
        echo $this->partial($layout, $data);
    }
}
