<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_code' => [
                'required',
                'string',
                'max:3',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
        ];
    }
}