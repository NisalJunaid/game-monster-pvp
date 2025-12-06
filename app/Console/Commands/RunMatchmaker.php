<?php

namespace App\Console\Commands;

use App\Domain\Pvp\LiveMatchmaker;
use Illuminate\Console\Command;

class RunMatchmaker extends Command
{
    protected $signature = 'pvp:matchmake {mode=ranked}';

    protected $description = 'Process the PvP queue manually (replaced by live sockets in-app)';

    public function __construct(private readonly LiveMatchmaker $matchmaker)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = (string) $this->argument('mode');
        $pairings = $this->matchmaker->processQueue($mode);

        if ($pairings === 0) {
            $this->info("No pairings created for {$mode} queue (live sockets handle most matches).");

            return self::SUCCESS;
        }

        $this->info(sprintf('Created %d battle(s) from %s queue.', $pairings, $mode));

        return self::SUCCESS;
    }
}
