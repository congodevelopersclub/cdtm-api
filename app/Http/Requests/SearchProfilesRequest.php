<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SearchProfilesRequest extends FormRequest
{
    private const ALLOWED = ['location', 'category', 'skills', 'page'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'skills'   => 'nullable|string|max:255',
            'page'     => 'nullable|integer|min:1',
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_diff(array_keys($this->query()), self::ALLOWED) as $key) {
                    $validator->errors()->add((string) $key, "The {$key} parameter is not allowed.");
                }
            },
        ];
    }
}
