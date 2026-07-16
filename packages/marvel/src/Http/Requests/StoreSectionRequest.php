<?php

namespace Marvel\Http\Requests;

use CodeZero\UniqueTranslation\UniqueTranslationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type' => 'required|string|max:100|exists:section_types,type',
            'title' => 'required|array',
            'title.*' => ['required', 'string', 'max:50', UniqueTranslationRule::for('sections', 'title')],
            'is_active' => 'nullable|in:0,1',
            'title_visible' => 'nullable|in:0,1',
            'order' => 'nullable|integer',
            'setting' => 'nullable|array',
            'setting.front' => 'nullable|array',
            'setting.back' => 'nullable|array',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('with_product')) {
                $setting = $this->input('setting', []);
                $back = $setting['back'] ?? [];
                $allowedKeys = ['slug'];
                $extraKeys = array_diff(array_keys($back), $allowedKeys);

                if (!empty($extraKeys)) {
                    $validator->errors()->add('setting.back', 'When with_product is true, only "slug" is allowed in setting.back.');
                }
            }
        });
    }
}
