<?php

namespace Joelfrens\HomeAutomation\Reminders\Exceptions;

use RuntimeException;
use Throwable;

/** The AI could not read the letter. The message is safe to show to the family. */
class ExtractionFailed extends RuntimeException
{
    public static function aiUnavailable(Throwable $previous): self
    {
        return new self('The AI could not read this right now. Try again later, or add the event by hand.', previous: $previous);
    }

    public static function unexpectedAnswer(): self
    {
        return new self('The AI gave an answer we could not understand. Try again, or add the event by hand.');
    }
}
