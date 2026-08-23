<?php

namespace Marvel\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Models\DigitalAsset;

class DigitalAssetCreateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file'          => ['required', 'file', 'mimes:' . implode(',', config('digital.allowed_mimes')), 'max:' . config('digital.max_upload_kb')],
            'type'          => ['sometimes', 'string', Rule::in(DigitalAsset::ACTIVE_TYPES)],
            'original_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order'    => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
