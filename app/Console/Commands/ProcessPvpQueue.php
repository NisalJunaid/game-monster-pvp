<?php

namespace App\Console\Commands;

use App\Domain\Pvp\LiveMatchmaker;
use Illuminate\Console\Command;

class ProcessPvpQueue extends Command
{
    protected $signature = 'pvp:process-queue';

    protected $description = 'Process queued PvP players for ranked and casual ladders as a safety net.';

    public function __construct(private readonly LiveMatchmaker $matchmaker)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ranked = $this->matchmaker->processQueue('ranked');
        $casual = $this->matchmaker->processQueue('casual');

        $this->info(sprintf('Processed queues => ranked: %d pairing(s), casual: %d pairing(s).', $ranked, $casual));

        return self::SUCCESS;
    }
}
