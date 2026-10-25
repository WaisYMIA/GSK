<?php

namespace App\Support\Content;

use RuntimeException;

// Thrown when submitted content or media fails validation. $errors is keyed by field name.
class ContentInvalid extends RuntimeException
{
    /** @param array<string, array<int, string>> $errors */
    public function __construct(public readonly array $errors)
    {
        $first = '';
        foreach ($errors as $messages) {
            $first = (string) ($messages[0] ?? '');
            break;
        }
        parent::__construct($first !== '' ? $first : 'Content is not valid.');
    }
}
