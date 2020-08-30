<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Database\Exception;

class QueryExecutionError extends QueryError
{
    /** @var array<string, mixed>|mixed[] */
    private array $diagnostics;

    /**
     * QueryExecutionError constructor.
     * @param string $message
     * @param array<string, mixed> $diagnostics
     * @param \Throwable|null $previous
     * @param string $query
     */
    public function __construct(string $message, array $diagnostics, \Throwable $previous = null, string $query = '')
    {
        parent::__construct($message, $query, $previous);
        $this->diagnostics = $diagnostics;
    }

    /**
     * @return array<string, mixed>|mixed[]
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }
}
