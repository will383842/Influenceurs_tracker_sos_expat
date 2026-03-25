<?php

namespace App\Jobs;

use App\Models\Influenceur;
use App\Services\AiEmailGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateOutreachEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(
        private int $influenceurId,
        private int $step = 1,
    ) {}

    public function handle(AiEmailGenerationService $service): void
    {
        $inf = Influenceur::find($this->influenceurId);
        if (!$inf) return;

        $result = $service->generate($inf, $this->step);
        if (!$result) {
            Log::debug('GenerateOutreachEmailJob: no email generated', [
                'influenceur_id' => $this->influenceurId,
                'step'           => $this->step,
            ]);
        }
    }
}
