<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UpdateProfileRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'headline' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],

           // Skills
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['array'],
            'skills.*.name' => ['required', 'string', 'max:100'],
            'skills.*.proficiency' => ['nullable', 'integer', 'min:1', 'max:5'],
            'skills.*.years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],

            // Projects
            'projects' => ['sometimes', 'array'],
            'projects.*' => ['array'],
            'projects.*.id' => [
                'nullable', 'integer',
                Rule::exists('projects', 'id')->where('profile_id', $this->route('profile')->id),
            ],
            'projects.*.title' => ['required', 'string', 'max:255'],
            'projects.*.description' => ['nullable', 'string'],
            'projects.*.link' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
