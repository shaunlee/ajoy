<?php

class EasyView extends ViewHelper implements IAjoyView
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
    public $minify = false;

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

    private function renderFile($filename, $fn)
    {
        $args = array_slice(func_get_args(), 2);
        foreach ($args as $arg)
            extract($arg);

        ob_start();
        include $filename;
        $ctx = ob_get_clean();

        if (is_callable($fn))
            $ctx = $fn($ctx);

        if ($this->minify)
            $ctx = $this->minify($ctx);

        return $ctx;
    }

    public function render($template, array $context = array(), $return = false)
    {
        $filename = $this->themesPath . '/' . app()->get('theme') . '/' . $template . '.php';
        if (!file_exists($filename))
            $filename = $this->viewsPath . '/' . $template . '.php';
        if (!file_exists($filename))
            app()->raise('Views file with name "' . $this->viewsPath . '/' . $template . '.php" does not exists.');

        $ctx = call_user_func_array(array($this, 'renderFile'), array($filename, function($ctx) {
            if (!empty($this->layouts)) {
                $layout = array_pop($this->layouts);
                $ctx = $this->render($layout, array(), true);
            }
            return $ctx;
        }, app()->locals(), $this->context, $context));

        if ($return)
            return $ctx;

        echo $ctx;
    }

    public function renderPartial($template, array $context = array(), $return = false)
    {
        $filename = $this->themesPath . '/' . app()->get('theme') . '/' . $template . '.php';
        if (!file_exists($filename))
            $filename = $this->viewsPath . '/' . $template . '.php';
        if (!file_exists($filename))
            app()->raise('Views file with name "' . $this->viewsPath . '/' . $template . '.php" does not exists.');

        $ctx = call_user_func_array(array($this, 'renderFile'),
            array($filename, null, app()->locals(), $this->context, $context));

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

        $ctx = call_user_func_array(array($this, 'renderFile'), array($filename, null, $options));

        if ($this->minify)
            $ctx = $this->minify($ctx);

        return $ctx;
    }

    public function minify($content)
    {
        $content = preg_replace('/\s+(\w+=)/', ' $1', $content);
        $content = preg_replace('/(\s+>|>\s+)/', '>', $content);
        $content = preg_replace('/\s+</', '<', $content);
        return $content;
    }

}
