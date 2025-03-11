<?php

namespace App\Http\Requests\API\QuizQuestion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuizQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled via middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'quiz_id' => 'sometimes|exists:quizzes,id',
            'question' => 'sometimes|string|max:1000',
            'type' => 'sometimes|string|in:multiple_choice,true_false,short_answer',
            'difficulty' => 'sometimes|string|in:easy,medium,hard',
            'points' => 'sometimes|integer|min:1',
            'options' => 'sometimes|required_if:type,multiple_choice|array',
            'options.*' => 'required_if:type,multiple_choice|string',
            'correct_answer' => 'sometimes|string',
            'explanation' => 'sometimes|nullable|string',
            'order' => 'sometimes|integer|min:0',
            'metadata' => 'sometimes|nullable|array'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'quiz_id.exists' => 'The selected quiz does not exist.',
            'question.max' => 'The question text cannot exceed 1000 characters.',
            'type.in' => 'The question type must be multiple choice, true/false, or short answer.',
            'difficulty.in' => 'The difficulty must be easy, medium, or hard.',
            'points.min' => 'The points value must be at least 1.',
            'options.required_if' => 'Options are required for multiple choice questions.',
            'options.*.required_if' => 'Each option must have a value.',
        ];
    }
}