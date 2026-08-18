<?php

declare(strict_types=1);

if (! function_exists('agency_url')) {
    function agency_url(string $module = 'dashboard', array $parameters = []): string
    {
        if ($module === 'login') {
            return route('login', $parameters);
        }

        return route('agency.show', array_merge(['module' => $module], $parameters));
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }
}
