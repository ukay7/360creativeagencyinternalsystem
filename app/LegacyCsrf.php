<?php

declare(strict_types=1);

namespace AgencyOS;

final class Csrf
{
    public static function token(): string
    {
        return csrf_token();
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
    }
}
