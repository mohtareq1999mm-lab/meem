<?php

namespace Marvel\Http\Requests;

use CodeZero\UniqueTranslation\UniqueTranslationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;


class CreateFaqsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'faq_title'       => ['required', 'array'],
            'faq_title.*'       => ['required', 'string', 'min:3', 'max:1000',  UniqueTranslationRule::for('faqs')],
            'faq_description' => ['required', 'array'],
            'faq_description.*' => ['required', 'string', 'min:3', 'max:1000', UniqueTranslationRule::for('faqs')],
            'shop_id'         => ['nullable', 'exists:shops,id'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
