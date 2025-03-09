<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseHint extends Model
{
    protected $fillable = [
        'exercise_id',
        'hint',
        'order',
        'xp_penalty',
    ];

    protected $casts = [
        'order' => 'integer',
        'xp_penalty' => 'integer',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function shouldProvide(int $attemptCount): bool
    {
        // Provide hints based on attempt count and hint order
        return $attemptCount >= ($this->order + 1);
    }

    public function applyPenalty(User $user): void
    {
        // Deduct XP for using the hint
        if ($this->xp_penalty > 0) {
            $user->awardXp(
                -$this->xp_penalty,
                'hint_used',
                $this->exercise_id
            );
        }
    }
}