<?php
/**
 * SSO
 */

namespace tw88\sso;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Events\Dispatcher;
use tw88\sso\Listener;

return function (Dispatcher $events, Bus $bus) {
    $events->subscribe(Listener\AddConfigureMiddleware::class);
};
