<?php


namespace Marvel\Database\Repositories;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Settings;
use Marvel\Traits\MediaManager;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SettingsRepository extends BaseRepository
{
    use MediaManager;
    /**
     * Configure the Model
     **/
    public function model()
    {
        return Settings::class;
    }

    public function getApplicationSettings()
    {
        return $this->getAppSettingsData();
    }

    private function getAppSettingsData()
    {
        // $config = new MarvelVerification();
        // $apiData = $config->jsonSerialize();
        // try {
        //     $licenseKey = $config->getPrivateKey();
        //     $last_checking_time = $config->getLastCheckingTime() ?? Carbon::now();
        //     $lastCheckingTimeDifferenceFromNow = Carbon::parse($last_checking_time)->diffInMinutes(Carbon::now());
        //     if ($lastCheckingTimeDifferenceFromNow > 20) {
        //         $apiData = $config->verify($licenseKey)->jsonSerialize();
        //     }
        // } catch (Exception $e) {
        // }
        // return [
        //     'last_checking_time' => Carbon::now(),
        //     'trust' => $apiData['trust'] ?? false,
        // ];

        return $this->first();
    }

    public function updateSetting($data, $id)
    {
        try {
            DB::beginTransaction();
            $setting = $this->first();
            $setting->update($data->except('logo', 'favicon'));
            if (isset($data['logo'])) {
                if (!$this->updateSingleImage($data, 'logo', $setting, 'logo-setting', 'settings')) {
                    throw new HttpException(422, 'Logo upload failed, please check the file format or size.');
                }
            }
            if (isset($data['favicon'])) {
                if (!$this->updateSingleImage($data, 'favicon', $setting, 'favicon-setting', 'settings')) {
                    throw new HttpException(422, 'Logo upload failed, please check the file format or size.');
                }
            }
            // if(isset($data['promotion_video_url'])){
            //     $setting->addMedia($data['promotion_video_url'])->toMediaCollection('promotion-video-setting');
            // }
            DB::commit();
            return $setting;

        } catch (Exception $e) {
            DB::rollBack();
            throw new HttpException(500, 'Logo upload failed, please check the file format or size.');

        }
    }

}
