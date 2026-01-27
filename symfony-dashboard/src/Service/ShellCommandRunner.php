<?php

namespace App\Service;

use Symfony\Component\Process\Process;

class ShellCommandRunner
{
    public function run(array $command, ?int $timeout = null): string
    {
        $process = new Process($command);
        
        if ($timeout !== null) {
            $process->setTimeout($timeout);
        }
        
        $process->mustRun();

        return $process->getOutput();
    }
}
