<?php

namespace Marvel\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Faqs;
use Marvel\Database\Repositories\FaqsRepository;
use Marvel\Enums\Permission;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\CreateFaqsRequest;
use Marvel\Http\Requests\UpdateFaqsRequest;
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
    public $repository;

    public function __construct(FaqsRepository $repository)
    {
        $this->repository = $repository;
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
        $language = $request->language ?? DEFAULT_LANGUAGE;
        $faqs = $this->fetchFAQs($request)->where('language', $language)->paginate($limit)->withQueryString();
        $data = FaqResource::collection($faqs)->response()->getData(true);
        return formatAPIResourcePaginate($data);
    }


    /**
     * fetchFAQs
     *
     * @param  Request $request
     * @return object
     */
    public function fetchFAQs(Request $request)
    {
        $language = $request->language ?? DEFAULT_LANGUAGE;
        try {
            $user = $request->user();

            if ($user) {
                switch ($user) {
                    case $user->hasPermissionTo(Permission::SUPER_ADMIN):
                        return $this->repository
                            ->with('shop')
                            ->whereNotNull('id')
                            ->where('language', $language);
                        break;

                    case $user->hasPermissionTo(Permission::STORE_OWNER):
                        if ($this->repository->hasPermission($user, $request->shop_id)) {
                            return $this->repository
                                ->with('shop')
                                ->where('shop_id', '=', $request->shop_id)
                                ->where('language', $language);
                        } else {
                            return $this->repository
                                ->with('shop')
                                ->where('user_id', '=', $user->id)
                                ->where('language', $language)
                                ->whereIn('shop_id', $user->shops->pluck('id'));
                        }
                        break;

                    case $user->hasPermissionTo(Permission::STAFF):
                        // if ($this->repository->hasPermission($user, $request->shop_id)) {
                        return $this->repository
                            ->with('shop')
                            ->where('shop_id', '=', $request->shop_id)
                            ->where(
                                'language',
                                $language
                            );
                        // }
                        break;

                    default:
                        return $this->repository
                            ->with('shop')
                            ->where('language', $language)
                            ->whereNotNull('id');
                        break;
                }
            } else {
                if ($request->shop_id) {
                    return $this->repository
                        ->with('shop')
                        ->where('shop_id', '=', $request->shop_id)
                        ->where('language', $language)
                        ->whereNotNull('id');
                } else {
                    return $this->repository
                        ->with('shop')
                        ->where('language', $language)
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
    public function store(CreateFaqsRequest $request)
    {
        try {
            return $this->repository->storeFaqs($request);
            // return $this->repository->create($validatedData);
        } catch (MarvelException $e) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE, $e->getMessage());
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
            $faq = $this->repository->with('shop')->findOrFail($id);
            return new FaqResource($faq);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND, $e->getMessage());
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
            $request["id"] = $id;
            return $this->updateFaqs($request);
        } catch (MarvelException $e) {
            throw new MarvelException(COULD_NOT_UPDATE_THE_RESOURCE, $e->getMessage());
        }
    }

    /**
     * updateFaqs
     *
     * @param  UpdateFaqsRequest $request
     * @return void
     */
    public function updateFaqs(UpdateFaqsRequest $request)
    {
        $faqs = $this->repository->findOrFail($request['id']);
        return $this->repository->updateFaqs($request, $faqs);
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
            if ($user && ($user->hasPermissionTo(Permission::SUPER_ADMIN) || $user->hasPermissionTo(Permission::STORE_OWNER) || $user->hasPermissionTo(Permission::STAFF))) {
                return $this->repository->findOrFail($id)->delete();
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND, $e->getMessage());
        }
    }
}
