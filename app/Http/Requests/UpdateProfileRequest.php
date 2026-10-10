<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\LinkType;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateProfileRequest',
    title: 'Update Profile Request',
    description: 'Request payload for updating a user profile',
    properties: [
        new OA\Property(property: 'name', type: 'string', description: 'The profile name', example: 'John Doe'),
        new OA\Property(property: 'bio', type: 'string', description: 'The profile bio', example: 'Product designer based in Lille.'),
        new OA\Property(property: 'headline', type: 'string', description: 'The profile headline', example: 'Senior Product Designer'),
        new OA\Property(property: 'location', type: 'string', description: 'The profile location', example: 'Lille, France'),
        new OA\Property(
            property: 'category_id',
            type: 'integer',
            nullable: true,
            description: 'ID of the category to assign to this profile',
            example: 1
        ),
        new OA\Property(
            property: 'skills',
            description: 'Skills linked to this profile',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: 'Skill name', example: 'Laravel'),
                    new OA\Property(property: 'proficiency', type: 'integer', description: 'Skill proficiency level (1-5)', example: 4),
                    new OA\Property(property: 'years_experience', type: 'integer', description: 'Years of experience with the skill', example: 3),
                ],
                type: 'object'
            )
        ),
        new OA\Property(
            property: 'projects',
            description: 'Projects linked to this profile',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: ['integer','null'], description:'Project ID (nullable for new projects)', example:null),
                    new OA\Property(property:'title', type:'string', description:'Project title', example:'Portfolio Website'),
                    new OA\Property(property:'description', type:['string','null'], description:'Project description (nullable)', example:'A personal portfolio website built with Laravel.'),
                    new OA\Property(property:'link', type:['string','null'], description:'Project link (nullable)', example:'https://portfolio.example.com'),
                ],
                type:'object'
            )
        ),
    ]
)]
class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $links = collect($this->input('links', []))
            ->only(array_column(LinkType::cases(), 'value'))
            ->map(fn ($url) => $this->normalize($url))
            ->filter()
            ->all();

        $this->merge(['links' => $links]);
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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],

           // Skills
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['array'],
            'skills.*.name' => ['required', 'string', 'max:100'],
            'skills.*.proficiency' => ['nullable', 'integer', 'min:1', 'max:5'],
            'skills.*.years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],

            // Projects
            'projects' => ['sometimes', 'array'],
            'projects.*' => ['array'],
            'projects.*.title' => ['required', 'string', 'max:255'],
            'projects.*.description' => ['nullable', 'string'],
            'projects.*.link' => ['nullable', 'string', 'max:2048'],

            // links
            'links' => ['sometimes', 'array'],
            ...$this->linkRules(),
        ];
    }

    /**
     * Generate validation rules for each link type.
     *
     * @return array<string, array<int, string>>
     */
    private function linkRules(): array
    {
        $rules = [];

        foreach (LinkType::cases() as $type) {
            $field = ['nullable', 'string', 'max:255', 'url:https'];

            if (($pattern = $type->pattern()) !== null) {
                $field[] = 'regex:' . $pattern;
            }

            $rules["links.{$type->value}"] = $field;
        }

        return $rules;
    }


    private function normalize(mixed $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        // Add a scheme if the user pasted "linkedin.com/in/..."
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (! $parts || $parts['host'] === '') {
            return $url; // let validation reject it
        }

        $host = strtolower($parts['host']);
        $path = rtrim($parts['path'] ?? '', '/');

        // Collapse localized LinkedIn subdomains (fr.linkedin.com -> www.linkedin.com)
        if (str_ends_with($host, 'linkedin.com')) {
            $host = 'www.linkedin.com';
        }

        // Force https, drop query string and fragment
        return 'https://' . $host . $path;
    }
}
