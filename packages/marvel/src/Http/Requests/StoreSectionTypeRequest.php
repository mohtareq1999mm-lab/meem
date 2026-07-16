<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionTypeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type' => 'required|string|max:100|unique:section_types,type',
        ];
    }
}
