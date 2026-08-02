<?php

declare(strict_types=1);

namespace App\Shared\Support;

final class InputMapper
{
    public static function map(array $input, array $databaseToApi): array
    {
        $data = [];
        foreach ($databaseToApi as $database => $api) {
            if (array_key_exists($api, $input)) {
                $data[$database] = $input[$api];
            }
        }

        return $data;
    }
}
