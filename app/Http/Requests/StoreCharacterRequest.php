<?php

namespace App\Http\Requests;

use App\Models\Character;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreCharacterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'personality' => ['required', 'string', 'max:500'],
            'voice' => ['required', Rule::in(array_keys(Character::VOICES))],
            'drawing' => [
                'required_without:prompt',
                'nullable',
                File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(10 * 1024),
            ],
            'prompt' => ['required_without:drawing', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'drawing.required_without' => 'Upload a drawing or describe your character.',
            'prompt.required_without' => 'Upload a drawing or describe your character.',
        ];
    }
}
