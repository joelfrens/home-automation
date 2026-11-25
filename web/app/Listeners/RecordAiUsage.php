<?php

namespace App\Listeners;

use App\Models\AiUsage;
use Laravel\Ai\Events\AgentPrompted;

/**
 * laravel/ai fires AgentPrompted after every AI call. Log tokens and cost, so the family can
 * see what AI costs (Settings → Profile shows this month's total).
 */
class RecordAiUsage
{
    public function handle(AgentPrompted $event): void
    {
        $usage = $event->response->usage;
        $model = $event->response->meta->model;

        AiUsage::create([
            'agent' => class_basename($event->prompt->agent),
            'provider' => $event->response->meta->provider,
            'model' => $model,
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'cost' => AiUsage::costFor($model, $usage->inputTokens, $usage->outputTokens),
        ]);
    }
}
