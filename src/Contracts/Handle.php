<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Database\Contracts;

interface Handle extends Quoter
{
    public const STATEMENT_NAME_PREFIX = 'makise_';
}
