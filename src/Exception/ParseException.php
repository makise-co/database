<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Database\Exception;

class ParseException extends FailureException
{
    public function __construct(string $message = '')
    {
        $message = "Parse error while splitting array" . (($message === '') ? '' : ": " . $message);
        parent::__construct($message);
    }
}
