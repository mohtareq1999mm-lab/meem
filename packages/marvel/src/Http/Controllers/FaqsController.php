<?php

namespace Marvel\Http\Controllers;

use Database\Seeders\FlashSaleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Faqs;
use Marvel\Database\Repositories\FaqsRepository;
use Marvel\Enums\Permission;
use Marvel\Enums\Role;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\CreateFaqsRequest;
use Marvel\Http\Requests\UpdateFaqsRequest;
use Marvel\Traits\ApiResponse;
use Marvel\Http\Resources\FaqResource;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * @OA\Schema(
 *     schema="Faq",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="faq_title", type="string", example="How to return?"),
 *     @OA\Property(property="slug", type="string", example="how-to-return"),
 *     @OA\Property(property="faq_description", type="string", example="You can return within 30 days."),
 *     @OA\Property(property="faq_type", type="string", example="global"),
 *     @OA\Property(property="issued_by", type="string", example="Admin"),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="translated_languages", type="array", @OA\Items(type="string", example="en"))
 * )
 */
class FaqsController extends CoreController
{
    use ApiResponse;
    public $repository;

    public function __construct(FaqsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware("permission:" . Permission::VIEW_FAQS, ["only" => ["index", "show"]]);
        $this->middleware("permission:" . Permission::CREATE_FAQ, ["only" => ["store"]]);
        $this->middleware("permission:" . Permission::UPDATE_FAQ, ["only" => ["update", "reorder"]]);
        $this->middleware("permission:" . Permission::DELETE_FAQ, ["only" => ["destroy"]]);
    }


    /**
     * @OA\Get(
     *     path="/faqs",
     *     operationId="getFaqs",
     *     tags={"FAQs"},
     *     summary="List all FAQs",
     *     description="Retrieve a paginated list of FAQs. Filterable by shop_id and language.",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="shop_id",
     *         in="query",
     *         description="Filter FAQs by Shop ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Filter by language code",
     *         @OA\Schema(type="string", default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of FAQs retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Faq")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $limit = $request->limit ? $request->limit : 10;
        $order = $request->order;
        $sortedBy = $request->sortedBy ?? 'asc';

        $faqsQuery = $this->fetchFAQs($request);

        if ($order && in_array($order, ['id', 'faq_title', 'faq_type', 'issued_by', 'status', 'created_at', 'updated_at'])) {
            $faqsQuery = $faqsQuery->orderBy($order, $sortedBy === 'desc' ? 'desc' : 'asc');
        }

        $faqs = $faqsQuery->paginate($limit)->withQueryString();
        $faqData = FaqResource::collection($faqs)->response()->getData(true);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, [
            "data" => $faqData['data'] ?? [],
            "page" => $faqData['meta']['current_page'] ?? 0,
            "current_page" => $faqData['meta']['current_page'] ?? 0,
            "from" => $faqData['meta']['from'] ?? 0,
            "to" => $faqData['meta']['to'] ?? 0,
            "last_page" => $faqData['meta']['last_page'] ?? 0,
            "path" => $faqData['meta']['path'] ?? "",
            "per_page" => $faqData['meta']['per_page'] ?? 0,
            "total" => $faqData['meta']['total'] ?? 0,
            "next_page_url" => $faqData['links']['next'] ?? "",
            "prev_page_url" => $faqData['links']['prev'] ?? "",
            "last_page_url" => $faqData['links']['last'] ?? "",
            "first_page_url" => $faqData['links']['first'] ?? "",
        ]);
    }


    /**
     * fetchFAQs
     *
     * @param Request $request
     * @return object
     */
    public function fetchFAQs(Request $request)
    {
        //        $language = $request->language ?? DEFAULT_LANGUAGE;
        try {
            $user = $request->user();

            if ($user) {
                switch ($user) {
                    case $user->hasRole(Role::SUPER_ADMIN):
                        return $this->repository
                            ->with('shop')
                            ->whereNotNull('id');
                        //                            ->where('language', $language);
                        break;

                    case $user->hasRole(Role::STORE_OWNER):
                        if ($this->repository->hasPermission($user, $request->shop_id)) {
                            return $this->repository
                                ->with('shop')
                                ->where('shop_id', '=', $request->shop_id);
                            //                                ->where('language', $language);
                        } else {
                            return $this->repository
                                ->with('shop')
                                ->where('user_id', '=', $user->id)
                                //                                ->where('language', $language)
                                ->whereIn('shop_id', $user->shops->pluck('id'));
                        }
                        break;

                    case $user->hasRole(Role::STAFF):
                        // if ($this->repository->hasPermission($user, $request->shop_id)) {
                        return $this->repository
                            ->with('shop')
                            ->where('shop_id', '=', $request->shop_id);
                        //                            ->where(
                        //                                'language',
                        //                                $language
                        //                            );
                        // }
                        break;

                    default:
                        return $this->repository
                            ->with('shop')
                            //                            ->where('language', $language)
                            ->whereNotNull('id');
                        break;
                }
            } else {
                if ($request->shop_id) {
                    return $this->repository
                        ->with('shop')
                        ->where('shop_id', '=', $request->shop_id)
                        //                        ->where('language', $language)
                        ->whereNotNull('id');
                } else {
                    return $this->repository
                        ->with('shop')
                        //                        ->where('language', $language)
                        ->whereNotNull('id');
                }
            }
        } catch (MarvelException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG, $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/faqs",
     *     operationId="storeFaq",
     *     tags={"FAQs"},
     *     summary="Create a new FAQ",
     *     description="Add a new FAQ. Accessible by Staff for their shop, Store Owners, and Super Admins.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"faq_title", "faq_description"},
     *             @OA\Property(property="faq_title", type="string", example="How to return a product?"),
     *             @OA\Property(property="faq_description", type="string", example="You can return any product within 30 days..."),
     *             @OA\Property(property="shop_id", type="integer", example=1),
     *             @OA\Property(property="language", type="string", example="en")
     *         )
     *     ),
     *     @OA\Response(response=201, description="FAQ created", @OA\JsonContent(ref="#/components/schemas/Faq")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $faq = $this->repository->storeFaqs($request);
            return $this->apiResponse(FAQ_CREATED_SUCCESSFULLY, 201, true, FaqResource::make($faq));
        } catch (MarvelException $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    /**
     * @OA\Get(
     *     path="/faqs/{id}",
     *     operationId="getFaqById",
     *     tags={"FAQs"},
     *     summary="Get single FAQ",
     *     description="Retrieve details of a specific FAQ by ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="FAQ ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="FAQ found", @OA\JsonContent(ref="#/components/schemas/Faq")),
     *     @OA\Response(response=404, description="FAQ not found")
     * )
     */
    public function show($id)
    {
        try {
            $faq = $this->repository->findOrFail($id);
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, FaqResource::make($faq));
        } catch (MarvelException $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }

    /**
     * @OA\Put(
     *     path="/faqs/{id}",
     *     operationId="updateFaq",
     *     tags={"FAQs"},
     *     summary="Update FAQ",
     *     description="Update details of an existing FAQ. Accessible by staff of the shop, Store Owners, or Super Admins.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="FAQ ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="faq_title", type="string"),
     *             @OA\Property(property="faq_description", type="string"),
     *             @OA\Property(property="language", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="FAQ updated", @OA\JsonContent(ref="#/components/schemas/Faq")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="FAQ not found")
     * )
     */
    public function update(UpdateFaqsRequest $request, $id)
    {
        try {
            $request->merge(['id' => $id]);

            $faq = $this->updateFaqs($request);
            return $this->apiResponse(FAQ_UPDATED_SUCCESSFULLY, 200, true, FaqResource::make($faq));
        } catch (MarvelException $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    /**
     * updateFaqs
     *
     * @param UpdateFaqsRequest $request
     * @return void
     */
    public function updateFaqs(UpdateFaqsRequest $request)
    {
        $faqs = $this->repository->findOrFail($request['id']);
        $faqsUpdate = $this->repository->updateFaqs($request, $faqs);
        return $faqsUpdate;
    }

    /**
     * @OA\Delete(
     *     path="/faqs/{id}",
     *     operationId="deleteFaq",
     *     tags={"FAQs"},
     *     summary="Delete FAQ",
     *     description="Remove an FAQ from the system. Accessible by authorized Staff, Owners or Admins.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="FAQ deleted successfully"),
     *     @OA\Response(response=404, description="FAQ not found")
     * )
     */
    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'faqs' => 'required|array',
                'faqs.*' => 'required|exists:faqs,id',
            ]);
            $this->repository->reorder($request->faqs);
            return $this->apiResponse(FAQS_REORDERED_SUCCESSFULLY, 200, true);
        } catch (MarvelException $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    public function destroy($id, Request $request)
    {
        $request->merge(['id' => $id]);
        return $this->deleteFaq($request);
    }

    public function deleteFaq(Request $request)
    {
        try {
            $id = $request->id;
            $user = $request->user();
            if ($user && ($user->hasPermissionTo(Permission::DELETE_FAQ))) {
                $this->repository->findOrFail($id)->delete();
                return $this->apiResponse(FAQ_DELETED_SUCCESSFULLY, 200, true);
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }
}