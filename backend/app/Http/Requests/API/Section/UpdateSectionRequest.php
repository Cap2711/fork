<?php

namespace App\Http\Requests\API\Section;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-learning-paths');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'lesson_id' => [
                'sometimes',
                'integer',
                Rule::exists('lessons', 'id')
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'order' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('sections')
                    ->where('lesson_id', $this->lesson_id ?? $this->section->lesson_id)
                    ->ignore($this->section->id)
            ],
            'exercises' => ['sometimes', 'array'],
            'exercises.*.id' => ['sometimes', 'integer', 'exists:exercises,id'],
            'exercises.*.type' => [
                'required_with:exercises',
                'string',
                Rule::in(['multiple_choice', 'fill_blank', 'matching', 'writing', 'speaking'])
            ],
            'exercises.*.content' => ['required_with:exercises', 'array'],
            'exercises.*.answers' => ['required_with:exercises', 'array'],
            'exercises.*.order' => [
                'required_with:exercises',
                'integer',
                'min:1',
                'distinct'
            ],
            'exercises.*._remove' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'lesson_id.exists' => 'The selected lesson does not exist.',
            'title.required' => 'A section title is required.',
            'title.max' => 'The title cannot be longer than 255 characters.',
            'content.required' => 'The section content is required.',
            'order.unique' => 'This order number is already taken in this lesson.',
            'order.min' => 'The order must be at least 1.',
            'exercises.*.type.required_with' => 'Each exercise must have a type.',
            'exercises.*.type.in' => 'Invalid exercise type provided.',
            'exercises.*.content.required_with' => 'Each exercise must have content.',
            'exercises.*.answers.required_with' => 'Each exercise must have answers.',
            'exercises.*.order.required_with' => 'Each exercise must have an order.',
            'exercises.*.order.distinct' => 'Exercise orders must be unique.',
            'exercises.*.id.exists' => 'One or more exercises do not exist.',
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        if ($this->has('order') && $this->order !== $this->section->order) {
            $this->reorderSections();
        }

        // Prepare exercises for updating
        if ($this->has('exercises')) {
            $this->prepareExercises();
        }
    }

    /**
     * Reorder sections when the order changes
     */
    private function reorderSections(): void
    {
        $lessonId = $this->lesson_id ?? $this->section->lesson_id;
        $currentOrder = $this->section->order;
        $newOrder = $this->order;

        if ($newOrder > $currentOrder) {
            // Moving down: decrement orders between current and new
            \App\Models\Section::where('lesson_id', $lessonId)
                ->whereBetween('order', [$currentOrder + 1, $newOrder])
                ->decrement('order');
        } else {
            // Moving up: increment orders between new and current
            \App\Models\Section::where('lesson_id', $lessonId)
                ->whereBetween('order', [$newOrder, $currentOrder - 1])
                ->increment('order');
        }
    }

    /**
     * Prepare exercises for updating
     */
    private function prepareExercises(): void
    {
        $this->merge([
            'exercises' => collect($this->exercises)
                ->map(function ($exercise) {
                    // Mark exercises for removal if _remove is true
                    if (isset($exercise['_remove']) && $exercise['_remove']) {
                        return ['id' => $exercise['id'], '_remove' => true];
                    }
                    return $exercise;
                })
                ->toArray()
        ]);
    }
}