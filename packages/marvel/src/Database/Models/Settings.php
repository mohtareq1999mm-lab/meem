<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Settings extends Model implements HasMedia
{
    use HasTranslations, InteractsWithMedia;

    protected $table = 'settings';

    public $translatable = [
        'site_name',
        'site_desc',
        'meta_desc',
        'site_copy_right',
    ];

    public $fillable = [
        'site_name',
        'site_desc',
        'meta_desc',
        'site_copy_right',
        'logo',
        'favicon',
        'site_email',
        'email_support',
        'facebook',
        'instagram',
        'linkedin',
        'promotion_video_url',
        'youtube',
        'phone',
        'fast_shipping_page_publish',
        'options',
    ];

    protected $casts = [
        'options'   => 'array',
    ];

}