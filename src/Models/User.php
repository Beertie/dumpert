<?php

namespace Dumpert\Models;

final class User
{
    /** @var string */
    public $userName;

    /**
     * User constructor.
     * @param string $userName
     */
    public function __construct(string $userName)
    {
        $this->userName = $userName;
    }
}