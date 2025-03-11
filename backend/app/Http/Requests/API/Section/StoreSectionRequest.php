<?php

namespace App\Http\Requests\API\Section;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('lessons', 'id')
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'order' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('sections')
                    ->where('lesson_id', $this->lesson_id)
            ],
            'exercises' => ['sometimes', 'array'],
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
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'lesson_id.required' => 'The lesson is required.',
            'lesson_id.exists' => 'The selected lesson does not exist.',
            'title.required' => 'A section title is required.',
            'title.max' => 'The title cannot be longer than 255 characters.',
            'content.required' => 'The section content is required.',
            'order.required' => 'The section order is required.',
            'order.unique' => 'This order number is already taken in this lesson.',
            'order.min' => 'The order must be at least 1.',
            'exercises.*.type.required_with' => 'Each exercise must have a type.',
            'exercises.*.type.in' => 'Invalid exercise type provided.',
            'exercises.*.content.required_with' => 'Each exercise must have content.',
            'exercises.*.answers.required_with' => 'Each exercise must have answers.',
            'exercises.*.order.required_with' => 'Each exercise must have an order.',
            'exercises.*.order.distinct' => 'Exercise orders must be unique.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If order is not provided, set it to the next available order number
        if (!$this->has('order')) {
            $this->merge([
                'order' => $this->getNextOrder()
            ]);
        }

        // If exercises are provided but don't have order, set sequential orders
        if ($this->has('exercises')) {
            $exercises = collect($this->exercises)->map(function ($exercise, $index) {
                if (!isset($exercise['order'])) {
                    $exercise['order'] = $index + 1;
                }
                return $exercise;
            })->toArray();

            $this->merge(['exercises' => $exercises]);
        }
    }

    /**
     * Get the next available order number for the lesson
     */
    private function getNextOrder(): int
    {
        if (!$this->has('lesson_id')) {
            return 1;
        }

        return \App\Models\Section::where('lesson_id', $this->lesson_id)
            ->max('order') + 1;
    }
}