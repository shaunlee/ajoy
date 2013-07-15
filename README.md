# ajoy

Ajoy web framework, yet another php web framework just for fun.

### examples

    app()->configure(array(
        'app root' => ROOT,
    ));

    app()->locals(array(
        'title' => 'Ajoy web framework',
    ));

    app()->get('/', funciton ($req, $res) {
        $res->send(app()->get('title'));
    });

    app()->run();

Is it simple enough?

