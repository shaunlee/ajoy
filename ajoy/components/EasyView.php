<?php

class EasyView extends AjoyComponent implements IAjoyView
{

    /**
     *
     */
    private $context = array();

    /**
     *
     */
    private $layouts = array();

    /**
     *
     */
    private $variables = array();

    /**
     *
     */
    private $viewsPath;

    /**
     *
     */
    private $themesPath;

    /**
     *
     */
    public function init()
    {
        $this->viewsPath = app()->get('app root') . '/views';
        $this->themesPath = app()->get('app root') . '/themes';
    }

    public function extending($layout)
    {
        array_push($this->layouts, $layout);
    }

    public function block($variable)
    {
        array_push($this->variables, $variable);
        ob_start();
    }

    public function endblock()
    {
        $ctx = ob_get_clean();
        $variable = array_pop($this->variables);
        $this->context[$variable] = $ctx;
    }

    public function render($template, array $context = array(), $return = false)
    {
        $filename = $this->themesPath . '/' . app()->get('theme') . '/' . $template . '.php';
        if (!file_exists($filename))
            $filename = $this->viewsPath . '/' . $template . '.php';
        if (!file_exists($filename))
            app()->raise('Views file with name "' . $this->viewsPath . '/' . $template . '.php" does not exists.');

        extract(app()->locals());
        extract($this->context);
        extract($context);

        ob_start();
        include $filename;
        $ctx = ob_get_clean();

        if (!empty($this->layouts)) {
            $layout = array_pop($this->layouts);
            $ctx = $this->render($layout, array(), true);
        }

        if ($return)
            return $ctx;

        echo $ctx;
    }

    /**
     *
     */
    public function widget($name, array $options = array())
    {
        $widgetPath = str_replace('.', '/', $name) . '.php';
        $filename = $this->themesPath . '/' . app()->get('theme') . '/widgets/' . $widgetPath;
        if (!file_exists($filename))
            $filename = app()->get('app root') . '/views/widgets/' . $widgetPath;
        if (!file_exists($filename))
            $filename = app()->get('ajoy root') . '/widgets/' . $widgetPath;
        if (!file_exists($filename))
            app()->raise('Widget with name "' . $name . '" does not exists.');

        extract($options);
        ob_start();
        include $filename;
        $content = ob_get_clean();

        $content = preg_replace('/\s+(\w+=)/', ' $1', $content);
        $content = preg_replace('/\s+>/', '>', $content);
        $content = preg_replace('/(>)\s+|\s+(<)/', '$1$2', $content);
        return $content;
    }

    public function __call($method, $arguments)
    {
        $helper = ViewHelper::instance();
        if (method_exists($helper, $method))
            return call_user_func_array(array($helper, $method), $arguments);

        throw new Exception('Call to undefined method EasyView::' . $method . '()');
    }
}
