<?php

use Flarum\Database\Migration;

return Migration::addColumns('tags', [
    'moderator_role_id' => ['integer', 'nullable' => true, 'default' => null],
]);
