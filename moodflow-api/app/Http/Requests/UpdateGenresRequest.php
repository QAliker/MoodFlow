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
            'genre_ids'   => ['required', 'array', 'min:1', 'max:3'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
        ];
    }
}
