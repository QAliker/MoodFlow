<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoodStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:week,month'],
        ];
    }
}
