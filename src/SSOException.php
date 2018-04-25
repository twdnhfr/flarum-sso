<?php

namespace tw88\sso;

use Exception;

class SSOException extends Exception
{

    /**
     * @var array
     */
    protected $messages;

    /**
     * @param array $messages
     */
    public function __construct(array $messages)
    {
        $this->messages = $messages;
        parent::__construct('SingleSO Exception: ' . implode("\n", $messages));
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }
}
