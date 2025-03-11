<?php

namespace App\Http\Requests\API\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'required|string',
            'time_limit' => 'nullable|integer|min:1', // Time limit in minutes
            'passing_score' => 'nullable|integer|min:1|max:100', // Percentage required to pass
            'difficulty' => 'required|string|in:beginner,intermediate,advanced',
            'status' => 'required|string|in:draft,published,archived',
            'section_id' => 'nullable|exists:sections,id',
            'order' => 'nullable|integer|min:1',
            'metadata' => 'nullable|array'
        ];
    }
}
