<?php

namespace App\Exceptions;

use RuntimeException;

/** Fehler, deren Text direkt dem Kunden angezeigt werden darf. */
class CheckoutException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $soldOut = false, public readonly ?int $ticketTypeId = null)
    {
        parent::__construct($message);
    }
}
