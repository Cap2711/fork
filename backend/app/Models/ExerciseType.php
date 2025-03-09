<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'component_name',
    ];

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    public function getValidationRules(): array
    {
        // Define validation rules for user answers based on exercise type
        switch ($this->name) {
            case 'multiple_choice':
                return [
                    'answer' => 'required|string',
                ];

            case 'translate':
            case 'listen_type':
                return [
                    'answer' => 'required|string|max:500',
                ];

            case 'word_bank':
            case 'fill_in_blank':
                return [
                    'answer' => 'required|array',
                    'answer.*' => 'required|string',
                ];

            case 'match_pairs':
                return [
                    'answer' => 'required|array',
                    'answer.*' => 'required|array:source,target',
                    'answer.*.source' => 'required|string',
                    'answer.*.target' => 'required|string',
                ];

            case 'speak':
                return [
                    'answer' => 'required|string',
                    'confidence_score' => 'required|numeric|min:0|max:1',
                ];

            default:
                return [
                    'answer' => 'required',
                ];
        }
    }
}