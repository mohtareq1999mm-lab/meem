<?php

namespace Marvel\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateStaticSectionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'sometimes|array',
            'title.en' => 'sometimes|string|max:255',
            'title.*' => 'sometimes|nullable|string|max:255',
            'content' => 'sometimes|array',
            'content.en' => 'array',
            'content.ar' => 'array',
        ];
    }

    /**
     * When `content` is supplied it must be an associative locale-keyed object.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $content = $this->input('content');

            if (is_array($content) && array_is_list($content)) {
                $validator->errors()->add('content', __(STATIC_SECTION_CONTENT_INVALID));
            }
        });
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}