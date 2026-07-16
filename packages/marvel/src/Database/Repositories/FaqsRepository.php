<?php


namespace Marvel\Database\Repositories;

use Exception;
use Marvel\Database\Models\Faqs;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Illuminate\Http\Request;
use Marvel\Database\Models\Shop;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FaqsRepository extends BaseRepository
{

    /**
     * @var array
     */
    protected $fieldSearchable = [
        'faq_title' => 'like',
    ];

    /**
     * @var array
     */
    protected $dataArray = [
        'faq_title',
        'faq_description',
        'status',
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
        return Faqs::class;
    }



    /**
     * storeFaqs
     *
     * @param  mixed $request
     * @return void
     */
    public function storeFaqs($request)
    {
        try {
           

            $faqs                    = [];
            $faqs['faq_title']       = $request['faq_title'];
            $faqs['faq_description'] = $request['faq_description'];
            $faqs = $this->create($faqs);
            return $faqs;
        } catch (Exception $th) {
            throw new Exception(SOMETHING_WENT_WRONG, 500);
        }
    }


    /**
     * updateFaqs
     *
     * @param  mixed $request
     * @param  mixed $faqs
     * @return void
     */
    public function updateFaqs(Request $request, Faqs $faqs)
    {
        try {
          $faqs->update($request->only($this->dataArray));

            return $faqs;
        } catch (Exception $e) {
            throw new Exception(SOMETHING_WENT_WRONG, 500);
        }
    }

    public function reorder(array $faqs)
    {
        try {
            $this->setNewOrder($faqs);
        } catch (Exception $e) {
            throw new HttpException(500, $e->getMessage());
        }
    }
}
