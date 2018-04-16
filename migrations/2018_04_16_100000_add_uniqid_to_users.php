<?php

use Flarum\Database\Migration;

return Migration::addColumns('users', [
    'uniqid' => ['string', 'length' => 50, 'nullable' => true],
]);
