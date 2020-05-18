<?php

use Flarum\Extend;
use Illuminate\Contracts\Events\Dispatcher;
use tw88\sso\Listener;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),

    function (Dispatcher $events) {
        $events->subscribe(Listener\AddConfigureMiddleware::class);
        $events->subscribe(Listener\MailNotificator::class);
    },
];
