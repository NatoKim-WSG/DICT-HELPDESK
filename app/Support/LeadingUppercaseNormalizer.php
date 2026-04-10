<?php

namespace App\Support;

final class LeadingUppercaseNormalizer
{
    public static function normalize(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        return mb_strtoupper(mb_substr($trimmed, 0, 1)).mb_substr($trimmed, 1);
    }
}
