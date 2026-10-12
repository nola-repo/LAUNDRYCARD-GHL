<?php

namespace App\Console\Commands;

use App\Exceptions\Integration\IntegrationException;
use App\Services\BranchMappingService;
use App\Services\LaundryCardClient;
use Illuminate\Console\Command;

class RefreshStores extends Command
{
    protected $signature = 'integration:stores';

    protected $description = 'Refresh the LaundryCard store registry from the API.';

    public function handle(BranchMappingService $mappings, LaundryCardClient $client): int
    {
        try {
            $r = $mappings->refreshStores($client);
        } catch (IntegrationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Stores: {$r['total']} total, {$r['new']} new, {$r['missing']} missing.");

        return self::SUCCESS;
    }
}
