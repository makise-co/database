<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\SqlCommon\Exception;

class QueryError extends \Error
{
    protected string $query = "";

    public function __construct(string $message, string $query = "", \Throwable $previous = null)
    {
        if ($query !== "") {
            $this->query = $query;
        }
        parent::__construct($message, 0, $previous);
    }

    final public function getQuery(): string
    {
        return $this->query;
    }

    public function __toString(): string
    {
        if ($this->query === "") {
            return parent::__toString();
        }

        $msg = $this->message;
        $this->message .= "\nCurrent query was {$this->query}";
        $str = parent::__toString();
        $this->message = $msg;
        return $str;
    }
}
