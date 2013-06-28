<?php

defined('ROOT') or define('ROOT', realpath(dirname(__file__) . '/..'));

require ROOT . '/../ajoy/ajoy.php';

app()->configure(array(
    'app root' => ROOT,
    'modules' => array('blog'),
));

app()->configure('development', array(
    'app root' => ROOT,
    'modules' => array('blog', 'test'),
));

app()->get('/', function ($req, $res) {
    $res->render('index', array(
        'refreshed_at' => time(),
    ));
});

app()->locals(array(
    'title' => 'Ajoy Web Framework',
));

app()->locals('author', 'Shaun Li <shonhen@gmail.com>');

app()->run();
