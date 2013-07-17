<?php

app()->module('/blog', array(
    'get' => array(
        '' => function ($req, $res) {
            $res->send('Blog entries');
        },
        '/<int:id>' => function ($req, $res) {
            $res->send('Blog entry ' . $req->query('id'));
        },
    ),
));
