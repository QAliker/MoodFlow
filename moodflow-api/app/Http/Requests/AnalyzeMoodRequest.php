<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeMoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $validModes = implode(',', array_keys(config('activity-modes')));

        return [
            'source'        => ['required', 'string', 'in:emoji,slider,passive'],
            'emotion'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'energy'        => ['required', 'integer', 'between:0,100'],
            'valence'       => ['required', 'numeric', 'between:-1,1'],
            'intensity'     => ['required', 'integer', 'between:0,100'],
            'lat'           => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lon'           => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'activity_mode' => ['sometimes', 'nullable', 'string', "in:{$validModes}"],
        ];
    }
}
