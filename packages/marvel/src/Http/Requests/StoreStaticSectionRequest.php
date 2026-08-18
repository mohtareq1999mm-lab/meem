<?php

namespace Marvel\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreStaticSectionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|array',
            'title.en' => 'required|string|max:255',
            'title.*' => 'nullable|string|max:255',
            'content' => 'required|array',
            'content.en' => 'array',
            'content.ar' => 'array',
        ];
    }

    /**
     * The top level of `content` must be an associative locale-keyed object.
     *
     * A top-level JSON list would trigger Spatie's single-locale translation
     * branch, so it is rejected here before it ever reaches the model.
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