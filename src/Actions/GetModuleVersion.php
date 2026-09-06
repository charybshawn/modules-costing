<?php

namespace Cultpantry\Costing\Actions;

use Illuminate\Support\Facades\Process;

/**
 * The commit the running module code is actually built from -- read live
 * from the package's own git checkout rather than a hand-maintained version
 * string, so the dashboard indicator can't drift from what's deployed.
 */
class GetModuleVersion
{
    /**
     * @return array{commit: string, date: string}|null
     */
    public function handle(): ?array
    {
        $root = dirname(__DIR__);

        $result = Process::path($root)->run('git log -1 --format=%h%x09%as');

        if (! $result->successful()) {
            return null;
        }

        [$commit, $date] = explode("\t", trim($result->output()));

        return ['commit' => $commit, 'date' => $date];
    }
}
