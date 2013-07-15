<?php

class AjoyCommandHandler extends AjoyComponent
{
    public $urlPattern = '/command';

    private $handlers = array();

    /**
     *
     */
    public function register()
    {
        if (func_num_args() === 0)
            app()->raise('Expected `command` and `function` or a batch of them.');

        $args = func_get_args();

        if (is_string($args[0])) {
            $command = array_shift($args);
            $fn = array_shift($args);
            $middlewares = $args;
            $this->handlers[$command] = array(
                'middlewares' => $middlewares, 'fn' => $fn);
        } elseif (is_array($args[0])) {
            $batch = array_shift($args);
            $middlewares = $args;
            foreach ($batch as $command => $fn)
                $this->handlers[$command] = array(
                    'middlewares' => $middlewares, 'fn' => $fn);
        }
    }

    /**
     *
     */
    public function handle($command, $data)
    {
        foreach ($this->handlers as $rc => $handler) {
            if ($rc === $command) {
                foreach ($handler['middlewares'] as $fn)
                    call_user_func($fn, app()->request, app()->response);

                return call_user_func($handler['fn'], $data);
            }
        }
        app()->raise('There is no command handler for `' . $command . '`.');
    }

    public function init()
    {
        app()->post($this->urlPattern, function ($req, $res) {
            $result = array();
            foreach ($req->bodies() as $command => $data)
                $result[$command] = app()->command->handle($command, $data);
            $res->json($result);
        });
    }

}
