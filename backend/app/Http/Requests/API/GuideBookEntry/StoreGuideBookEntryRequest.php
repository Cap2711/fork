<?php

namespace App\Http\Requests\API\GuideBookEntry;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuideBookEntryRequest extends FormRequest
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
            'content' => 'required|string',
            'category' => 'nullable|string|max:100',
            'level' => 'nullable|string|in:beginner,intermediate,advanced',
            'status' => 'required|string|in:draft,published,archived',
            'related_entry_ids' => 'nullable|array',
            'related_entry_ids.*' => 'exists:guide_book_entries,id'
        ];
    }
}
