<?php

/*
 * What AI calls cost, so Settings can show "AI this month". Prices are US dollars per
 * million tokens (input, output), from the providers' price lists. Local models are free.
 * Every call is logged in the ai_usage table (App\Listeners\RecordAiUsage).
 */
return [

    'prices' => [
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00],
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00],
        'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50],
    ],

];
