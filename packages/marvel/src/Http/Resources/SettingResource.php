<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Request;

class SettingResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
             "site_name" => $this->getTranslation('site_name', app()->getLocale()),
                "site_desc" => $this->getTranslation('site_desc', app()->getLocale()),
                "meta_desc" => $this->getTranslation('meta_desc', app()->getLocale()),
                "site_copy_right" => $this->getTranslation('site_copy_right', app()->getLocale()),
                "logo" =>$this->getFirstMediaUrl('logo-setting'),
                "favicon" =>$this->getFirstMediaUrl('favicon-setting'),
                "site_email" => $this->site_email,
                "email_support" => $this->email_support,
                "facebook" => $this->facebook,
                "instagram" => $this->instagram,
                "linkedin" => $this->linkedin,
                "promotion_video_url" => $this->promotion_video_url,
                'youtube' => $this->youtube,
                'phone' => $this->phone
        ];
    }
}
