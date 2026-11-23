<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $agent
 * @property string|null $provider
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string $cost
 * @property Carbon $created_at
 */
class AiUsage extends Model
{
    protected $table = 'ai_usage';

    public const UPDATED_AT = null;

    protected $fillable = ['agent', 'provider', 'model', 'input_tokens', 'output_tokens', 'cost'];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** Dollars for this many tokens on this model (0 for local or unknown models). */
    public static function costFor(?string $model, int $inputTokens, int $outputTokens): float
    {
        $price = config("household-ai.prices.{$model}");

        if (! is_array($price)) {
            return 0.0;
        }

        return ($inputTokens * $price['input'] + $outputTokens * $price['output']) / 1_000_000;
    }
}
