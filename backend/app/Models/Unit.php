<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Unit extends Model
{
    protected $fillable = [
        'name',
        'description',
        'order',
        'difficulty',
        'is_locked',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    protected $with = ['lessons'];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserUnitProgress::class);
    }

    public function userLessonProgress(): HasManyThrough
    {
        return $this->hasManyThrough(UserLessonProgress::class, Lesson::class);
    }

    public function isCompletedByUser(User $user): bool
    {
        return $this->userProgress()
            ->where('user_id', $user->id)
            ->where('level', '>', 0)
            ->exists();
    }

    public function isAvailableForUser(User $user): bool
    {
        if (!$this->is_locked) {
            return true;
        }

        // First unit is always available
        if ($this->order === 1) {
            return true;
        }

        // Check if previous unit is completed
        return Unit::where('order', $this->order - 1)
            ->first()
            ->isCompletedByUser($user);
    }

    public function getLevelForUser(User $user): int
    {
        return $this->userProgress()
            ->where('user_id', $user->id)
            ->value('level') ?? 0;
    }

    public function getCompletedLessonsCount(User $user): int
    {
        return $this->userLessonProgress()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->count();
    }
}