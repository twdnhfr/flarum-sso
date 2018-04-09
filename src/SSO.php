<?php

namespace tw88\sso;

use tw88\SSO\Broker;

class SSO extends Broker
{
    public function __construct($url, $broker, $secret)
    {
        parent::__construct($url, $broker, $secret);
        $this->attach(true);
    }
}
