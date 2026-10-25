<?php

namespace App\Support\Access;

use InvalidArgumentException;

final class Scope
{
    public const TYPES = ['global', 'organisation', 'programme', 'partner', 'case'];

    private function __construct(public readonly string $type, public readonly ?int $id)
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown scope type: {$type}");
        }
        if ($type !== 'global' && $id === null) {
            throw new InvalidArgumentException('A non-global scope needs an id.');
        }
    }

    public static function global(): self
    {
        return new self('global', null);
    }

    public static function organisation(int $id): self
    {
        return new self('organisation', $id);
    }

    public static function programme(int $id): self
    {
        return new self('programme', $id);
    }

    public static function partner(int $id): self
    {
        return new self('partner', $id);
    }

    public static function case(int $id): self
    {
        return new self('case', $id);
    }

    public static function of(string $type, ?int $id): self
    {
        return new self($type, $id);
    }
}
