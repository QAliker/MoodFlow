<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGenresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'genre_ids'   => ['required', 'array', 'min:3', 'max:5'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
        ];
    }
}
