<?php

namespace Marvel\Http\Requests;

use CodeZero\UniqueTranslation\UniqueTranslationRule;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class UpdateFaqsRequest extends FormRequest
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
        $id = $this->route('faq');
        return [
            'faq_title'         => ['sometimes', 'array'],
            'faq_title.*'       => ['sometimes', 'string', 'min:3', 'max:1000',  UniqueTranslationRule::for('faqs')->ignore($id)],
            'faq_description'   => ['sometimes', 'array'],
            'faq_description.*' => ['sometimes', 'string', 'min:3', 'max:1000'],
            'status'            => ['sometimes', 'in:0,1'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
