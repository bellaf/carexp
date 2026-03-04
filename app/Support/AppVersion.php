<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

class AppVersion
{
    private ?string $resolvedVersion = null;

    public function current(): string
    {
        if ($this->resolvedVersion !== null) {
            return $this->resolvedVersion;
        }

        $configuredVersion = config('app.version');

        if (is_string($configuredVersion) && $configuredVersion !== '') {
            return $this->resolvedVersion = $configuredVersion;
        }

        $monthResult = Process::path(base_path())
            ->run(['git', 'show', '-s', '--date=format:%Y-%m', '--format=%cd', 'HEAD']);
        $countResult = Process::path(base_path())
            ->run(['git', 'rev-list', '--count', 'HEAD']);

        if ($monthResult->successful() && $countResult->successful()) {
            $month = trim($monthResult->output());
            $count = trim($countResult->output());

            if ($month !== '' && $count !== '') {
                return $this->resolvedVersion = $month.'-'.$count;
            }
        }

        return $this->resolvedVersion = 'development';
    }
}
