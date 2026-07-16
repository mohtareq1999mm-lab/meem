<?php


namespace Marvel\Database\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeValue;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Resources\AttributeResource;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttributeRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name' => 'like',
    ];

    protected $dataArray = [
        'name',
        'slug',
    ];

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
            //
        }
    }
    /**
     * Configure the Model
     **/
    public function model()
    {
        return Attribute::class;
    }

    public function storeAttribute($request)
    {
        try {
            DB::beginTransaction();
            $request['slug'] = $this->makeSlug($request);
            $attribute = $this->create($request->only($this->dataArray));
            if (isset($request['values']) && count($request['values'])) {
                foreach ($request['values'] as  $value) {
                    $value['slug'] = $this->makeSlug($request);
                    AttributeValue::create([
                        'value' => $value['value'],
                        'attribute_id' => $attribute->id,
                        'slug' => $value['slug'],
                    ]);
                }
            }
            DB::commit();
            return $attribute->load(['values']);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw new HttpException(400, COULD_NOT_CREATE_THE_RESOURCE);
        }
    }

    public function updateAttribute($request, $attribute)
    {
        try {

            $request['slug'] = $this->makeSlug($request, 'slug', $attribute->id);


            $attribute->update($request->only($this->dataArray));
            if (isset($request['values']) && count($request['values'])) {
                $attribute->values()->delete();
                foreach ($request['values'] as  $value) {
                    $value['slug'] = $this->makeSlug($request);
                    AttributeValue::create([
                        'value' => $value['value'],
                        'attribute_id' => $attribute->id,
                        'slug' => $value['slug'],
                    ]);
                }
            }

            $attributeUpdated =  $this->with(['values'])->findOrFail($attribute->id);
            return $attributeUpdated;
        } catch (\Throwable $th) {
            throw new HttpException(400, COULD_NOT_UPDATE_THE_RESOURCE);
        }
    }
}