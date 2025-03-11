<?php

namespace App\Http\Requests\API\QuizQuestion;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizQuestionRequest extends FormRequest
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
            'quiz_id' => 'required|exists:quizzes,id',
            'question' => 'required|string|max:1000',
            'type' => 'required|string|in:multiple_choice,true_false,short_answer',
            'difficulty' => 'required|string|in:easy,medium,hard',
            'points' => 'required|integer|min:1',
            'options' => 'required_if:type,multiple_choice|array',
            'options.*' => 'required_if:type,multiple_choice|string',
            'correct_answer' => 'required|string',
            'explanation' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'quiz_id.required' => 'The quiz ID is required.',
            'quiz_id.exists' => 'The selected quiz does not exist.',
            'question.required' => 'The question text is required.',
            'question.max' => 'The question text cannot exceed 1000 characters.',
            'type.required' => 'The question type is required.',
            'type.in' => 'The question type must be multiple choice, true/false, or short answer.',
            'difficulty.required' => 'The difficulty level is required.',
            'difficulty.in' => 'The difficulty must be easy, medium, or hard.',
            'points.required' => 'The points value is required.',
            'points.min' => 'The points value must be at least 1.',
            'options.required_if' => 'Options are required for multiple choice questions.',
            'options.*.required_if' => 'Each option must have a value.',
            'correct_answer.required' => 'The correct answer is required.',
        ];
    }
}