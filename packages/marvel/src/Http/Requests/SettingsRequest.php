<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class SettingsRequest extends FormRequest
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
              "site_name" => ['required','array'],
              "site_name.*" => ['required','string','min:3' , "max:200"],
                "site_desc" => ['required','array'],
                "site_desc.*" => ['required','string','min:3' , "max:2000"],
                "meta_desc" => ['required','array'],
                "meta_desc.*" => ['required','string','min:3' , "max:2000"],
                "site_copy_right" => ['required','array'],
                "site_copy_right.*" => ['required','string','min:3' , "max:200"],
                "logo" =>['required',"image", "mimes:jpeg,png,jpg,gif,svg", "max:2048"],
                "favicon" =>['required',"image", "mimes:jpeg,png,jpg,gif,svg", "max:2048"],
                "site_email" => ['required','email'],
                "email_support" => ['required','email'],
                "facebook" => ['required','url'],
                "instagram" => ['required','url'],
                "linkedin" => ['required','url'],
                "promotion_video_url" => ['sometimes','url'],
                'youtube' => ['required','url'],
                'phone' => ['required','string'],
                'fast_shipping_page_publish' => ['required', 'in:0,1'],
            'options' => ['sometimes', 'array'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
