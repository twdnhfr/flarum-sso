<?php

use Flarum\Database\Migration;

return Migration::addColumns('users', [
    'uniqid' => ['string', 'nullable' => true],
]);
