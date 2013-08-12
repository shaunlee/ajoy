<?php

/**
 * Ajoy web framework, yet another php web framework just for fun.
 *
 * @author Shaun Li <shonhen@gmail.com>
 *
 * MIT Licensed
 */

/**
 *
 */
defined('AJOY_ENV') or define('AJOY_ENV', isset($_SERVER['AJOY_ENV']) ? $_SERVER['AJOY_ENV'] : 'development');

/**
 *
 */
class AjoyException extends Exception
{
}

/**
 *
 */
function ajoy_array_merge($array1, $array2)
{
    $args=func_get_args();
    $res=array_shift($args);
    while(!empty($args))
    {
        $next=array_shift($args);
        foreach($next as $k => $v)
        {
            if(is_integer($k))
                isset($res[$k]) ? $res[]=$v : $res[$k]=$v;
            else if(is_array($v) && isset($res[$k]) && is_array($res[$k]))
                $res[$k]=ajoy_array_merge($res[$k],$v);
            else
                $res[$k]=$v;
        }
    }
    return $res;
}

/**
 *
 */
abstract class AjoyComponent
{

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     *
     */
    public function init()
    {
    }

    /**
     * Get the singleton instance of the AjoyComponent
     *
     * @param AjoyComponent
     */
    public static function instance()
    {
        static $instances = array();
        $className = get_called_class();
        if (!isset($instances[$className])) {
            $instances[$className] = new $className();
        }
        return $instances[$className];
    }

}

/**
 *
 */
class AjoyRedis extends AjoyComponent
{
}

/**
 *
 */
class AjoyMemcached extends AjoyComponent
{
}

interface IAjoyLogger
{
    public function debug($message);
    public function error($message);
    public function info($message);
    public function warning($message);
}

/**
 *
 */
class AjoyLogger extends AjoyComponent implements IAjoyLogger
{

    /**
     *
     */
    public function init()
    {

    }

    /**
     *
     */
    public function debug($message)
    {
        #
    }

    /**
     *
     */
    public function error($message)
    {
        #
    }

    /**
     *
     */
    public function info($message)
    {
        #
    }

    /**
     *
     */
    public function warning($message)
    {
        #
    }

}

interface IAjoyDbCommand
{
    public function insert($table, array $fields);
    public function update($table, array $fields, array $conditions);
    public function delete($table, array $conditions);
}

interface IAjoyDbQuery
{
    public function one($sql);
    public function all($sql);
    public function scalar($sql);
}

interface IAjoyDatabase extends IAjoyDbCommand, IAjoyDbQuery
{
    public function begin();
    public function commit();
    public function rollback();
}

interface IAjoyCache
{
    public function exists($field);
    public function set($field, $value, $expires = 0);
    public function get($field, $default = null, $dependendcies = null);
    public function delete($field);
}

/**
 *
 */
class AjoyCache extends AjoyComponent implements IAjoyCache
{
    private $prefix;

    /**
     *
     */
    public function init()
    {
        $this->prefix = app()->get('app id');
    }

    /**
     *
     */
    public function exists($field)
    {
        return apc_exists($this->prefix . $field);
    }

    /**
     *
     */
    public function set($field, $value, $expires = 0)
    {
        apc_store($this->prefix . $field, $value, $expires);
    }

    /**
     *
     */
    public function get($field, $default = null, $dependendcies = null)
    {
        # TODO: dependendcies
        $value = apc_fetch($this->prefix . $field, $success);
        return $success ? $value : $default;
    }

    /**
     *
     */
    public function delete($field)
    {
        if ($value = $this->get($field)) {
            apc_delete($this->prefix . $field);
            return $value;
        }
    }

}

interface IAjoySession
{
    public function set($field, $value);
    public function get($field, $default = null);
    public function delete($field);
}

/**
 *
 */
class AjoySession extends AjoyComponent implements IAjoySession
{
    private $prefix;

    public static $MAX_TTL = 2592000; # 3600 * 24 * 30

    public function init()
    {
        $this->prefix = app()->get('app id');

        session_start();
    }

    public function set($field, $value)
    {
        $_SESSION[$this->prefix . $field] = $value;
    }

    public function get($field, $default = null)
    {
        return isset($_SESSION[$this->prefix . $field])
            ? $_SESSION[$this->prefix . $field]
            : $default;
    }

    public function delete($field)
    {
        if (isset($_SESSION[$this->prefix . $field])) {
            $value = $_SESSION[$this->prefix . $field];
            unset($_SESSION[$this->prefix . $field]);
            return $value;
        }
    }

    public function expire()
    {
        #app()->response->cookie('');
    }

    public function destroy()
    {
        $keys = array_keys($_SESSION);
        $n = strlen($this->prefix);
        foreach ($keys as $key) {
            if (!strncmp($key, $this->prefix, $n))
                unset($_SESSION[$key]);
        }
        session_regenerate_id(true);
    }
}

/**
 *
 */
class AjoyValidation extends AjoyComponent
{
    private $form;
    public $lastErrors;

    public function validate($form, $conditions)
    {
        $this->lastErrors = array();
        $this->form = $form;

        foreach ($conditions as $condition) {
            $fields = preg_split('/,\s*/', array_shift($condition));
            $op = array_shift($condition);

            $methodName = 'filter' . $op;
            if (!method_exists($this, $methodName))
                $methodName = 'valid' . $op;
            if (!method_exists($this, $methodName))
                app()->raise('Validator or filter with name "' . $op . '" does not exists.');

            foreach ($fields as $field)
                if (!isset($this->lastErrors[$field])) {
                    $args = $condition;
                    array_unshift($args, $field);
                    call_user_func_array(array($this, $methodName), $args);
                }
        }

        if (empty($this->lastErrors))
            return $this->form;
    }

    private function get($field)
    {
        return isset($this->form[$field]) ? $this->form[$field] : '';
    }

    public function filterTrim($field)
    {
        $this->form[$field] = trim($this->get($field));
    }

    public function filterBoolean($field)
    {
        $this->form[$field] = !!$this->get($field);
    }

    public function filterSplit($field, $pattern = '/,/')
    {
        $this->form[$field] = preg_split($pattern, $this->get($field));
    }

    public function filterReplace($field, $from, $to)
    {
        $this->form[$field] = preg_replace($from, $to, $this->get($field));
    }

    public function validRequired($field, $message = 'Field `%s` is required.')
    {
        if ($this->get($field) === '')
            $this->lastErrors[$field] = sprintf($message, $field);
    }

    public function validRegex($field, $pattern, $message = 'Field `%s` is invalid.')
    {
        $value = $this->get($field);
        if ($value && !preg_match($pattern, $value))
            $this->lastErrors[$field] = sprintf($message, $field);
    }

    public function validEmail($field, $message = 'Invalid email format.')
    {
        $value = $this->get($field);
        if ($value && !preg_match('/^\w+@\w+(\.\w+){1,3}$/', $value))
            $this->lastErrors[$field] = sprintf($message, $field);
    }

    public function validNumber($field, $message = 'Invalid number.')
    {
        $value = $this->get($field);
        if ($value && !preg_match('/^\d+$/', $value))
            $this->lastErrors[$field] = sprintf($message, $field);
    }

    public function validAtLeastOne($field, $other_fields, $message = 'At least one of these fields required.')
    {
        $fields = preg_split('/,\s*/', $other_fields);
        array_unshift($fields, $field);
        $valid = false;
        foreach ($fields as $key) {
            if ($this->get($key) != '') {
                $valid = true;
                break;
            }
        }
        if (!$valid)
            $this->lastErrors[$field] = sprintf($message, $field);
    }

    public function validCurrency($field, $message = 'Invalid currency.')
    {
        $value = $this->get($field);
        if ($value && !preg_match('/^\d+(\.\d{1,2})?$/', $value))
            $this->lastErrors[$field] = sprintf($message, $field);
    }

    public function validComparison($field, $other_field, $operator = '==', $message = 'Invalid value.')
    {
        $value = $this->get($field);
        $other_value = $this->get($other_field);
        switch ($operator) {
            case '==':
                $matched = $value == $other_value;
                break;
            case '!=':
                $matched = $value != $other_value;
                break;
            case '>':
                $matched = $value > $other_value;
                break;
            case '<':
                $matched = $value < $other_value;
                break;
            case '>=':
                $matched = $value >= $other_value;
                break;
            case '<=':
                $matched = $value <= $other_value;
                break;
            default:
                app()->raise('Comparison validator operator "' . $operator . '" is invalid.');
        }
        if (!$matched)
            $this->lastErrors[$field] = sprintf($message, $field);
    }
}

/**
 *
 */
class AjoyRequest extends AjoyComponent
{

    /**
     * Get field from the requested http headers
     *
     * @param string $field
     *
     * @return string
     */
    public function get($field)
    {
        $field = strtoupper(str_replace(' ', '_', $field));
        return isset($_SERVER[$field]) ? $_SERVER[$field] : '';
    }

    /**
     * Check if request is specified content type
     *
     * @param $type regex
     *
     * @return boolean
     */
    public function is($type) {
        $ctype = $this->get('content type');
        return !!preg_match('`' . $type . '`', $ctype);
    }

    /**
     * Read field from the $_GET
     *
     * @param string $field
     * @param mixed $default
     *
     * @return string
     */
    public function query($field, $default = null)
    {
        return isset($_GET[$field]) ? $_GET[$field] : $default;
    }

    /**
     * Read field from the $_POST
     *
     * @param string $field
     * @param mixed $default
     *
     * @return string
     */
    public function body($field, $default = null)
    {
        return isset($_POST[$field]) ? $_POST[$field] : $default;
    }

    /**
     * 
     */
    public function bodies()
    {
        return $_POST;
    }

    /**
     * Read JSON from input
     *
     * @param boolean $plain whether return the plain text, or not
     *
     * @return null|array|string
     */
    public function json($plain = false)
    {
        if (!$this->is('json'))
            return null;

        $context = file_get_contents('php://input');
        if ($plain)
            return $context;

        return json_decode($context, true);
    }

    /**
     * Read field from the posted files
     *
     * @param string $field
     *
     * @return file???
     */
    public function file($field)
    {
        # TODO:
    }

    /**
     * Read field from the cookie
     *
     * @param string $field
     *
     * @return string
     */
    public function cookie($field, $default = null)
    {
        return isset($_COOKIE[$field]) ? $_COOKIE[$field] : $default;
    }
}

/**
 *
 */
class AjoyResponse extends AjoyComponent
{

    /**
     *
     */
    private $headers = array();

    /**
     * Set field to the response http headers
     *
     * @param string $field
     * @param string $value
     */
    public function set($field, $value)
    {
        $this->headers[$field] = $value;
    }

    /**
     * Get field from the http headers
     *
     * @param string $field
     *
     * @return string
     */
    public function get($field)
    {
        return isset($this->headers[$field]) ? $this->headers[$field] : null;
    }

    /**
     * Complete status with common status code definitions
     *
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     *
     * @param int|string $status
     *
     * @return string
     */
    public function completeStatus($code, $statusOnly = false)
    {
        static $commonStatus = array(
            200 => 'OK',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
        );
        return isset($commonStatus[$code])
            ? ($statusOnly ? $commonStatus[$code] : ($code . ' ' . $commonStatus[$code]))
            : $code;
    }

    /**
     * app()->response->headers->flush();
     */
    private function flushHeaders($headers)
    {
        $headers = array_merge($this->headers, $headers);
        foreach ($headers as $field => $value)
            header($field . ': ' . $value);
    }

    /**
     *
     * @see setcookie
     *
     * @param string $field
     * @param string $value
     * @param array $options
     */
    public function cookie($field, $value, array $options = null)
    {
        static $validOptions = array('expire', 'path', 'domain', 'secure', 'httponly');

        $args = array($field, $value);
        if ($options !== null)
            foreach ($validOptions as $key)
                if (isset($options[$key]))
                    $args[] = $options[$key];
        call_user_func_array('setcookie', $args);
    }

    /**
     *
     */
    public function redirect($url, $status = 302)
    {
        $this->flushHeaders(array(
            'Status' => $this->completeStatus($status),
            'Location' => $url,
        ));
        app()->end();
    }

    /**
     *
     */
    public function refresh()
    {
        $this->redirect(app()->request->get('request uri'));
    }

    /**
     *
     */
    public function json($context, $status = 200)
    {
        $this->flushHeaders(array(
            'Status' => $this->completeStatus($status),
            'Content-Type' => 'application/json; charset=utf-8',
        ));
        echo json_encode($context);
    }

    /**
     *
     */
    public function jsonp($context, $status = 200)
    {
        $this->flushHeaders(array(
            'Status' => $this->completeStatus($status),
            'Content-Type' => 'text/javascript; charset=utf-8',
        ));
        $content = json_decode($context);
        $cb = app()->request->query(app()->get('jsonp callback'));
        echo $cb . '(' . $content . ');';
    }

    /**
     *
     */
    public function render($template, $context = null, $status = 200)
    {
        if ($context === null)
            $context = array();
        elseif (is_numeric($context)) {
            $status = $context;
            $context = array();
        }

        $this->flushHeaders(array(
            'Status' => $this->completeStatus($status),
            'Content-Type' => 'text/html; charset=utf-8',
        ));
        return app()->view->render($template, $context);
    }

    /**
     *
     */
    public function send($content, $status = 200)
    {
        $this->flushHeaders(array(
            'Status' => $this->completeStatus($status),
            'Content-Type' => 'text/plain; charset=utf-8',
        ));
        echo $content;
    }

    /**
     *
     */
    public function binary($data, $mimetype, $status = 200)
    {
        $this->flushHeaders(array(
            'Status' => $this->completeStatus($status),
            'Content-Type' => $mimetype,
        ));
        echo $data;
    }

    /**
     *
     */
    public function sendfile($path_to_file)
    {
        switch (app()->get('web server')) {
        case 'nginx':
            break;
        case 'httpd':
        case 'apache':
            break;
        case 'lighttpd':
            break;
        }
        $this->flushHeaders(array(
            '#' => '#',
        ));
    }
}

interface IAjoyView
{
    public function render($template, array $context = array(), $return = false);
}

/**
 *
 */
class AjoyView extends AjoyComponent implements IAjoyView
{

    /**
     *
     */
    private $context;

    /**
     *
     */
    private $layouts = array();

    /**
     *
     */
    private $defines = array();

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

    /**
     *
     */
    public function encode($value)
    {
        return htmlspecialchars($value);
    }

    /**
     *
     */
    public function linebreaksbr($value)
    {
        return preg_replace('/(\r?\n)/', '<br>$1', $value);
    }

    /**
     *
     */
    public function datetime($value, $format = 'Y-m-d h:i:s')
    {
        return date($format, strtotime($value));
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

    /**
     * Begin extends with layouts
     *
     * @param string $template layout view
     */
    public function beginExtends($template)
    {
        $this->layouts[] = $template;
        ob_start();
    }

    /**
     * End extends with layouts
     *
     * @param string $varname parent container variable name
     *
     * @return string
     */
    public function endExtends($varname = 'content')
    {
        $content = $this->context[$varname] = ob_get_clean();

        $template = array_pop($this->layouts);
        $filename = $this->themesPath . '/' . app()->get('theme') . '/' . $template . '.php';
        if (!file_exists($filename))
            $filename = $this->viewsPath . '/' . $template . '.php';
        if (file_exists($filename)) {
            extract($this->context);
            include $filename;
        } else
            echo $content;
    }

    public function beginDefine($varname)
    {
        $this->defines[] = $varname;
        ob_start();
    }

    public function endDefine()
    {
        $varname = array_pop($this->defines);
        $content = $this->context[$varname] = ob_get_clean();
    }

    /**
     *
     * @param string $template
     * @param array $context
     * @param boolean $return whether it will return the content, or not
     *
     * @return string
     */
    public function render($template, array $context = array(), $return = false)
    {
        $this->context = ajoy_array_merge(app()->locals(), $context);

        $filename = $this->themesPath . '/' . app()->get('theme') . '/' . $template . '.php';
        if (!file_exists($filename))
            $filename = $this->viewsPath . '/' . $template . '.php';
        if (!file_exists($filename))
            app()->raise('Views file with name "' . $this->viewsPath . '/' . $template . '.php" does not exists.');

        extract($this->context);
        ob_start();
        include $filename;
        $content = ob_get_clean();

        if ($return)
            return $content;

        echo $content;
    }
}

/**
 *
 */
final class AjoyApp extends AjoyComponent
{

    /**
     *
     */
    private $events = array();

    /**
     *
     */
    private $routes = array(
        'GET' => array(),
        'POST' => array(),
        'PUT' => array(),
        'DELETE' => array(),
    );

    /**
     * Application settings
     */
    private $settings = array();

    /**
     * Get the singleton instance of the AjoyApp
     *
     * @return AjoyApp
     */
    public static function instance()
    {
        static $app;
        if (!$app) {
            $app = parent::instance();
            $app->configure(array(
                'web server' => 'nginx', # httpd(apache), lighttpd
                'jsonp callback' => 'callback',
                'ajoy root' => dirname(__file__),
                'app id' => sprintf('%x', crc32($_SERVER['SCRIPT_FILENAME'])),
                'components' => array(
                    'db' => array(
                        'interface' => 'IAjoyDatabase',
                        'class' => 'AjoyDatabase',
                    ),
                    'cache' => array(
                        'interface' => 'IAjoyCache',
                        'class' => 'AjoyCache',
                    ),
                    'session' => array(
                        'interface' => 'IAjoySession',
                        'class' => 'AjoySession',
                    ),
                    'logger' => array(
                        'interface' => 'IAjoyLogger',
                        'class' => 'AjoyLogger',
                    ),
                    'view' => array(
                        'interface' => 'IAjoyView',
                        'class' => 'AjoyView',
                    ),
                    'request' => array(
                        'class' => 'AjoyRequest',
                    ),
                    'response' => array(
                        'class' => 'AjoyResponse',
                    ),
                    'validator' => array(
                        'class' => 'AjoyValidation',
                    )
                ),
                'modules' => array(),
            ));
        }
        return $app;
    }

    /**
     *
     */
    public function __get($name)
    {
        static $components = array();

        if (!isset($components[$name]) && isset($this->settings['components'][$name])) {
            $cfg = $this->settings['components'][$name];
            if (!isset($cfg['class']))
                $this->raise('Missing `class` for component "' . $name . '".');
            if (isset($cfg['interface']) && !is_subclass_of($cfg['class'], $cfg['interface']))
                $this->raise('Component "' . $cfg['class'] . '" should be subclass of "' . $cfg['interface'] . '".');

            $comp = $components[$name] = $cfg['class']::instance();

            foreach ($cfg as $field => $value)
                if ($field !== 'interface' && $field !== 'class')
                    $comp->$field = $value;
            $comp->init();
        }

        if (isset($components[$name]))
            return $components[$name];

        $this->raise('Component with name "' . $name . '" does not exists.');
    }

    /**
     *
     */
    public function raise($message)
    {
        $this->emit('error', $message);
        throw new AjoyException($message);
    }

    /**
     * Check if now is debugging
     *
     * @param boolean
     */
    public function is_debug()
    {
        return AJOY_ENV !== 'production';
    }

    /**
     * Subscribe a channel for system events
     *
     * @param string $event
     * @param function $fn
     *
     * @return void
     */
    public function on($event, $fn = null)
    {
        if (is_string($event)) {
            foreach (preg_split('/,\s+/', $event) as $event)
                $this->events[] = array($event, $fn);
        } elseif (is_array($event)) {
            foreach ($event as $e => $fn)
                foreach (preg_split('/,\s+/', $e) as $e)
                    $this->events[] = array($e, $fn);
        }
    }

    /**
     * Publish a system event to the channel
     *
     * @param string $event
     * @param mixed $args optional
     */
    public function emit()
    {
        $args = func_get_args();
        $event = array_shift($args);
        if ($event === 'all')
            $this->raise('You are not going to emit "all".');

        foreach ($this->events as $e)
            if ($e[0] === 'all' || $e[0] === $event) {
                $fn = new ReflectionFunction($e[1]);
                $fn->invokeArgs($args);
            }
    }

    /**
     * Configure the Ajoy application settings, according to AJOY_ENV
     *
     * @param string $enviroment optional
     * @param array $settings
     *
     * @return void
     */
    public function configure($enviroment, $settings = null)
    {
        if (is_string($enviroment) && $enviroment !== AJOY_ENV)
            return;

        if (is_array($enviroment) && $settings === null)
            $settings = $enviroment;

        $this->settings = ajoy_array_merge($this->settings, $settings);
    }

    /**
     * Store variables for views
     *
     * @param string $field
     * @param mixed $value
     *
     * @return array|mixed|void
     */
    public function locals($field = null, $value = null)
    {
        static $locals = array();

        if ($field === null && $value === null)
            return $locals;

        if (is_string($field)) {
            if ($value)
                $locals[$field] = $value;
            elseif (isset($locals[$field]))
                return $locals[$field];
        } elseif (is_array($field)) {
            foreach ($field as $k => $v)
                $locals[$k] = $v;
        }
    }

    /**
     * /<field>/<int:field>/<float:field>/<path:field>
     */
    private function handler($method, $pattern, $fn)
    {
        $method = strtoupper($method);
        if (isset($this->routes[$method][$pattern]))
            $this->raise('Pattern with path "' . $pattern . '" has been exists.');

        $regex = str_replace('.', '\.', $pattern);
        $fields = array();

        if (preg_match_all('/<(((int|float|path):)?(\w+))>/', $regex, $matches))
            foreach ($matches[4] as $i => $field) {
                $fields[] = $field;
                $match = $matches[0][$i];
                $type = $matches[3][$i];
                $replaceTo = '';

                switch ($type) {
                    case 'int':
                        $replaceTo = '(\d+)';
                        break;
                    case 'float':
                        $replaceTo = '(\d+\.\d+)';
                        break;
                    case 'path':
                        $replaceTo = '(.+)';
                        break;
                    default:
                        $replaceTo = '([^/]+)';
                }
                $regex = preg_replace('`' . $match . '`', $replaceTo, $regex);
        }

        $this->routes[$method][$pattern] = array(
            'regex' => '`^' . $regex . '$`',
            'fn' => $fn,
            'fields' => $fields,
        );
    }

    /**
     * Convert 'a.b.c' to $settings['a']['b']['c']
     */
    public function set($field, $value)
    {
        $target = &$this->settings;
        foreach (explode('.', $field) as $path) {
            if (!isset($target[$path])) {
                $target[$path] = array();
            }
            $target = &$target[$path];
        }
        $target = $value;
    }

    /**
     * HTTP PUT or get application settings
     *
     * Convert 'a.b.c' to $settings['a']['b']['c']
     *
     */
    public function get($pattern, $fn = null)
    {
        if ($fn === null) {
            $target = $this->settings;
            foreach (explode('.', $pattern) as $path) {
                if (!is_array($target) || !isset($target[$path]))
                    return null;
                $target = $target[$path];
            }
            return $target;
        }

        $this->handler('GET', $pattern, $fn);
    }

    /**
     * HTTP POST
     */
    public function post($pattern, $fn)
    {
        $this->handler('POST', $pattern, $fn);
    }

    /**
     * HTTP PUT
     */
    public function put($pattern, $fn)
    {
        $this->handler('PUT', $pattern, $fn);
    }

    /**
     * HTTP DELETE
     */
    public function delete($pattern, $fn)
    {
        $this->handler('DELETE', $pattern, $fn);
    }

    /**
     *
     * @param $prefix
     * @param $middlewares
     * @param $handlers
     */
    public function module($handlers) {
        $prefix = '';
        $middlewares = array();

        $args = func_get_args();
        $handlers = array_pop($args);

        if (count($args) && is_string($args[0]) && !is_callable($args[0]))
            $prefix = array_shift($args);

        if (count($args))
            foreach ($args as $arg)
                if (is_callable($arg))
                    $middlewares[] = $arg;

        foreach (array('get', 'post', 'put', 'delete') as $method) {
            if (!isset($handlers[$method]))
                continue;

            foreach ($handlers[$method] as $pattern => $fn) {
                $cbs = $middlewares;
                if (is_callable($fn))
                    $cbs[] = $fn;
                elseif (is_array($fn))
                    foreach ($fn as $_)
                        $cbs[] = $_;
                $this->handler($method, $prefix . $pattern, $cbs);
            }
        }
    }

    /**
     *
     */
    private function dispatch()
    {
        if (isset($_GET['q']))
            $uri = $_GET['q'];
        elseif (isset($_SERVER['PATH_INFO']))
            $uri = $_SERVER['PATH_INFO'];
        elseif (isset($_SERVER['REQUEST_URI']))
            $uri = $_SERVER['REQUEST_URI'];
        else
            $uri = '/';

        $method = $_SERVER['REQUEST_METHOD'];
        if (!isset($this->routes[$method]))
            $this->error(404);

        $found = false;
        foreach ($this->routes[$method] as $pattern => $row) {
            if (preg_match($row['regex'], $uri, $matches)) {
                foreach ($row['fields'] as $i => $field)
                    $_GET[$field] = urldecode($matches[$i + 1]);

                $this->emit(strtolower($method) . ' ' . $pattern);

                $handlers = is_callable($row['fn']) ? array($row['fn']) : $row['fn'];
                foreach ($handlers as $fn)
                    call_user_func($fn, $this->request, $this->response);
                $found = true;
                break;
            }
        }
        if (!$found)
            $this->error(404);
    }

    public function error($code)
    {
        $this->emit('error' . $code);

        if (AJOY_ENV === 'production')
            $this->response->render('error' . $code, $code);
        else
            $this->response->send($this->response->completeStatus($code, true), $code);

        $this->end();
    }

    /**
     *
     */
    public function run()
    {
        $this->loadModules();

        spl_autoload_register(array($this, 'loadComponents'));
        spl_autoload_register(array($this, 'loadModels'));

        $this->emit('init');

        $this->dispatch();
    }

    /**
     *
     */
    public function loadComponents($className)
    {
        $compfile = '/components/' . $className . '.php';

        $filename = $this->get('app root') . $compfile;
        if (!file_exists($filename))
            $filename = $this->get('ajoy root') . $compfile;

        if (file_exists($filename))
            include $filename;
    }

    /**
     *
     */
    public function loadModels($className)
    {
        $paths = explode('.', $className);
        $filename = $this->get('app root') . '/models/' . implode('/', $paths) . '.php';
        if (file_exists($filename))
            include $filename;
    }

    /**
     *
     */
    public function loadModules()
    {
        static $loadedModules = array();

        foreach ($this->get('modules') as $module) {
            if (isset($loadedModules[$module]))
                continue;

            $paths = explode('.', $module);
            $modfile = '/modules/' . implode('/', $paths) . '.php';

            $filename = $this->get('app root') . $modfile;
            if (!file_exists($filename))
                $filename = $this->get('ajoy root') . $modfile;

            if (!file_exists($filename))
                $this->raise('Module with name "' . $module . '" does not exists.');

            include $filename;

            $loadedModules[$module] = true;
        }
    }

    /**
     *
     */
    public function end()
    {
        $this->emit('end');
        exit;
    }
}

function app()
{
    return AjoyApp::instance();
}
