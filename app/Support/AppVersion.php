<?php

namespace App\Support;

class AppVersion
{
    public function current(): ?string
    {
        $configuredVersion = config('app.version');

        return is_string($configuredVersion) && $configuredVersion !== ''
            ? $configuredVersion
            : null;
    }
}
