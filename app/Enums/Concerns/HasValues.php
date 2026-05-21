<?php

namespace App\Enums\Concerns;

trait HasValues
{
    public static function values(): array
    {
        $values = [];

        foreach ((new \ReflectionEnum(static::class))->getCases() as $case) {
            if (! $case instanceof \ReflectionEnumBackedCase) {
                continue;
            }

            $values[] = $case->getBackingValue();
        }

        return $values;
    }
}
