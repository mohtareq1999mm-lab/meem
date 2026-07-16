<?php

namespace Marvel\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Marvel\Console\MarvelVerification;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Profile;
use Marvel\Database\Models\Settings;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Wallet;
use Marvel\Database\Repositories\UserRepository;
use Marvel\Enums\Permission;
use Marvel\Enums\Role;
use Marvel\Events\ProcessUserData;
use Marvel\Exceptions\MarvelException;
use Marvel\Exceptions\MarvelNotFoundException;
use Marvel\Http\Requests\ChangePasswordRequest;
use Marvel\Http\Requests\LicenseRequest;
use Marvel\Http\Requests\UserCreateRequest;
use Marvel\Http\Requests\UserUpdateRequest;
use Marvel\Http\Resources\UserResource;
use Marvel\Mail\ContactAdmin;
use Marvel\Otp\Gateways\OtpGateway;
use Marvel\Traits\UsersTrait;
use Marvel\Traits\WalletsTrait;
use Spatie\Newsletter\Facades\Newsletter;

/**
 * @OA\Tag(name="User Management", description="User management endpoints [SUPER_ADMIN, STORE_OWNER, STAFF, CUSTOMER]")
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="shop_id", type="integer", nullable=true),
 *     @OA\Property(property="profile", type="object", @OA\Property(property="avatar", type="object"), @OA\Property(property="bio", type="string"), @OA\Property(property="contact", type="string")),
 *     @OA\Property(property="address", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="permissions", type="array", @OA\Items(type="object", @OA\Property(property="name", type="string", example="store_owner")))
 * )
 */
class UserController extends CoreController
{
    use WalletsTrait, UsersTrait;

    public $repository;
    private bool $applicationIsValid;
    private array $appData;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
        $this->applicationIsValid = $this->repository->checkIfApplicationIsValid();
    }
    /**
     * Validate user email from the link sent to the user.
     * @param  $id
     * @param  $hash
     * @return RedirectResponse
     */
    public function verifyEmail($id, $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }
        if ($user->hasVerifiedEmail()) {
            if ($user->hasPermissionTo(Permission::SUPER_ADMIN) || $user->hasPermissionTo(Permission::STORE_OWNER)) {
                return Redirect::away(config('shop.dashboard_url'));
            } else {
                return Redirect::away(config('shop.shop_url'));
            }
        }
        $user->markEmailAsVerified();
        if ($user->hasPermissionTo(Permission::SUPER_ADMIN) || $user->hasPermissionTo(Permission::STORE_OWNER)) {
            return Redirect::away(config('shop.dashboard_url'));
        } else {
            return Redirect::away(config('shop.shop_url'));
        }
    }
    /**
     * Send the email verification notification.
     *
     * @return JsonResponse
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->sendEmailVerificationNotification();
        return response()->json(['message' => 'Email verification link sent on your email id', 'success' => true]);
    }


    /**
     * @OA\Get(
     *     path="/admins",
     *     operationId="getAdmins",
     *     tags={"User Management"},
     *     summary="List Admin Users",
     *     description="Get list of all admin users. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="limit", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Admins retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function admins(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;
        $admins = $this->repository
            ->with(['profile', 'address', 'permissions'])
            ->where('is_active', true)
            ->whereHas('permissions', function ($query) {
                $query->where('name', Permission::SUPER_ADMIN);
            })
            ->paginate($limit);
        return $admins;
        // return UserResource::collection($admins);
    }

    /**
     * @OA\Get(
     *     path="/vendors",
     *     operationId="getVendors",
     *     tags={"User Management"},
     *     summary="List Vendor Users",
     *     description="Get list of all store owner/vendor users.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="limit", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Vendors retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function vendors(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;

        return $this->fetchVendors($request)->paginate($limit);
    }

    public function fetchVendors(Request $request)
    {
        $user = $request->user();
        $shopId = $request->shop_id ?? null;
        $exclude = is_numeric($request?->exclude) ? $request->exclude : null;
        $is_active = $request->is_active === 'true' ? true : false;
        $admins = User::permission(Permission::SUPER_ADMIN)->pluck('id')->toArray();
        if ($this->repository->hasPermission($user, $shopId)) {
            return $this->repository->permission(Permission::STORE_OWNER)
                ->where('is_active', $is_active)
                ->whereNotIn('id', $admins)
                ->when($exclude, fn($query) => $query->where('id', '!=', $exclude));
        }
        return $this->repository->permission(null);
    }

    /**
     * @OA\Get(
     *     path="/customers",
     *     operationId="getCustomers",
     *     tags={"User Management"},
     *     summary="List Customer Users",
     *     description="Get list of all customer users (excluding admins, vendors, and staff).",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="limit", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Customers retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function customers(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;
        $userWithOtherPermissions = User::permission([Permission::SUPER_ADMIN, Permission::STORE_OWNER, Permission::STAFF])->pluck('id')->toArray();
        return $this->repository->with(['profile', 'address', 'permissions'])
            ->permission(Permission::CUSTOMER)->whereNotIn('id', $userWithOtherPermissions)->paginate($limit);
    }



    /**
     * @OA\Get(
     *     path="/users",
     *     operationId="listUsers",
     *     tags={"User Management"},
     *     summary="List All Users",
     *     description="Get paginated list of all users. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="limit", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Users retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN")
     * )
     */
    public function index(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;
        return $this->repository->with(['profile', 'address', 'permissions'])->paginate($limit);
    }

    /**
     * @OA\Post(
     *     path="/users",
     *     operationId="createUser",
     *     tags={"User Management"},
     *     summary="Create User (Admin)",
     *     description="Create a new user with any role. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password"},
     *             @OA\Property(property="name", type="string", example="New User"),
     *             @OA\Property(property="email", type="string", format="email", example="newuser@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="permission", type="string", enum={"customer", "store_owner", "staff", "editor", "super_admin"}, example="customer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="User created successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(UserCreateRequest $request)
    {
        try {
            return $this->repository->storeUser($request);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Get(
     *     path="/users/{id}",
     *     operationId="getUser",
     *     tags={"User Management"},
     *     summary="Get User Details",
     *     description="Get a single user's full details. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show($id)
    {
        try {
            return $this->repository->with(['profile', 'address', 'shops', 'managed_shop'])->findOrFail($id);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/users/{id}",
     *     operationId="updateUser",
     *     tags={"User Management"},
     *     summary="Update User",
     *     description="Update user profile. SUPER_ADMIN can update any user; others can only update themselves.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Name"),
     *             @OA\Property(property="email", type="string", format="email", example="updated@example.com"),
     *             @OA\Property(property="profile", type="object"),
     *             @OA\Property(property="address", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="User updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(UserUpdateRequest $request, $id)
    {
        if ($request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
            $user = $this->repository->findOrFail($id);
            return $this->repository->updateUser($request, $user);
        } elseif ($request->user()->id == $id) {
            $user = $request->user();
            return $this->repository->updateUser($request, $user);
        }
        throw new AuthorizationException(NOT_AUTHORIZED);
    }

    /**
     * @OA\Delete(
     *     path="/users/{id}",
     *     operationId="deleteUser",
     *     tags={"User Management"},
     *     summary="Delete User",
     *     description="Permanently delete a user. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User deleted successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Get(
     *     path="/me",
     *     operationId="getCurrentUser",
     *     tags={"Authentication"},
     *     summary="Get Current User",
     *     description="Get the currently authenticated user's profile with wallet, addresses, and shop information",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="role", type="string", example="customer"),
     *             @OA\Property(property="profile", type="object"),
     *             @OA\Property(property="wallet", type="object"),
     *             @OA\Property(property="address", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            if (isset($user)) {
                $user = $this->repository
                    ->with(['profile', 'wallet', 'address', 'shops.balance', 'managed_shop.balance', 'roles'])
                    ->find($user->id)
                    ->loadLastOrder();
                $user->role = $user->roles->first()?->name;
                $user->unsetRelation('roles');
                return $user;
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    /**
     * @OA\Post(
     *     path="/token",
     *     operationId="login",
     *     tags={"Authentication"},
     *     summary="User Login",
     *     description="Authenticate user and get access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function token(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->where('is_active', true)->first();

        if (!$user || !Hash::check($request->password, $user->password) || !$this->applicationIsValid) {
            return ["token" => null, "permissions" => []];
        }
        $email_verified = $user->hasVerifiedEmail();
        event(new ProcessUserData());
        return [
            "token" => $user->createToken('auth_token')->plainTextToken,
            "permissions" => $user->getPermissionNames(),
            "email_verified" => $email_verified,
            "role" => $user->getRoleNames()->first()
        ];
    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     summary="User Logout",
     *     description="Revoke the current access token",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return true;
        }
        return $request->user()->currentAccessToken()->delete();
    }

    /**
     * @OA\Post(
     *     path="/register",
     *     operationId="register",
     *     tags={"Authentication"},
     *     summary="Register New User",
     *     description="Create a new user account and get access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="securePassword123"),
     *             @OA\Property(property="permission", type="string", enum={"customer", "store_owner"}, example="customer", description="User permission level")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully registered",
     *         @OA\JsonContent(ref="#/components/schemas/AuthResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not authorized (attempted to register as super_admin, editor, or staff - these roles are admin-assigned only)"
     *     )
     * )
     */
    public function register(UserCreateRequest $request)
    {
        try {
            Log::info('Register: Starting registration', ['email' => $request->email]);

            // Block privileged roles from self-registration
            // Only super_admin can assign these roles via user management
            $notAllowedPermissions = [Permission::SUPER_ADMIN, Permission::EDITOR, Permission::STAFF];
            if ((isset($request->permission->value) && in_array($request->permission->value, $notAllowedPermissions)) || (isset($request->permission) && in_array($request->permission, $notAllowedPermissions))) {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }

            // Start with customer permission and role
            $permissions = [Permission::CUSTOMER];
            $role = Role::CUSTOMER;

            // If store_owner permission is explicitly requested, add it
            $requestedPermission = isset($request->permission->value) ? $request->permission->value : $request->permission;
            if (isset($requestedPermission) && $requestedPermission === Permission::STORE_OWNER) {
                $permissions[] = Permission::STORE_OWNER;
                $role = Role::STORE_OWNER;
            }

            Log::info('Register: Creating user');

            // Mark user as verified by default on registration
            $user = $this->repository->create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);

            Log::info('Register: User created', ['user_id' => $user->id]);

            $user->givePermissionTo(array_unique($permissions));  // Ensure no duplicates
            Log::info('Register: Permission assigned');

            $user->assignRole($role);
            Log::info('Register: Role assigned');

            $user->load('roles'); // Refresh roles relation to fix null role issue
            $this->giveSignupPointsToCustomer($user->id);
            Log::info('Register: Signup points given');

            event(new ProcessUserData());
            Log::info('Register: Event dispatched');

            $token = $user->createToken('auth_token')->plainTextToken;
            Log::info('Register: Token created, returning response');

            return [
                "token" => $token,
                "permissions" => $user->getPermissionNames(),
                "role" => $user->getRoleNames()->first()
            ];
        } catch (\Exception $e) {
            Log::error('Register: Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @OA\Post(
     *     path="/ban-user",
     *     operationId="banUser",
     *     tags={"User Management"},
     *     summary="Ban User",
     *     description="Deactivate a user account and all their shops. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="integer", example=5, description="User ID to ban")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User banned successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN")
     * )
     */
    public function banUser(Request $request)
    {
        try {
            $user = $request->user();
            if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && $user->id != $request->id) {
                $banUser = User::find($request->id);
                $banUser->is_active = false;
                $banUser->save();
                $this->inactiveUserShops($banUser->id);
                return $banUser;
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
    function inactiveUserShops($userId)
    {
        $shops = Shop::where('owner_id', $userId)->get();
        foreach ($shops as $shop) {
            $shop->is_active = false;
            $shop->save();
            Product::where('shop_id', '=', $shop->id)->update(['status' => 'draft']);
        }
    }

    /**
     * @OA\Post(
     *     path="/active-user",
     *     operationId="activateUser",
     *     tags={"User Management"},
     *     summary="Activate User",
     *     description="Reactivate a banned user account. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="integer", example=5, description="User ID to activate")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User activated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN")
     * )
     */
    public function activeUser(Request $request)
    {
        try {
            $user = $request->user();
            if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && $user->id != $request->id) {
                $activeUser = User::find($request->id);
                $activeUser->is_active = true;
                $activeUser->save();
                return $activeUser;
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * @OA\Post(
     *     path="/forget-password",
     *     operationId="forgetPassword",
     *     tags={"Password Management"},
     *     summary="Request Password Reset",
     *     description="Send password reset email to user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset email sent",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
    public function forgetPassword(Request $request)
    {
        $user = $this->repository->findByField('email', $request->email);
        if (count($user) < 1) {
            return ['message' => NOT_FOUND, 'success' => false];
        }
        $tokenData = DB::table('password_resets')
            ->where('email', $request->email)->first();
        if (!$tokenData) {
            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => Str::random(16),
                'created_at' => Carbon::now()
            ]);
            $tokenData = DB::table('password_resets')
                ->where('email', $request->email)->first();
        }

        if ($this->repository->sendResetEmail($request->email, $tokenData->token)) {
            return ['message' => CHECK_INBOX_FOR_PASSWORD_RESET_EMAIL, 'success' => true];
        } else {
            return ['message' => SOMETHING_WENT_WRONG, 'success' => false];
        }
    }
    /**
     * @OA\Post(
     *     path="/verify-forget-password-token",
     *     operationId="verifyForgetPasswordToken",
     *     tags={"Password Management"},
     *     summary="Verify Password Reset Token",
     *     description="Verify that a password reset token is valid",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "token"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="token", type="string", example="abc123xyz")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token verification result",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
    public function verifyForgetPasswordToken(Request $request)
    {
        $tokenData = DB::table('password_resets')->where('token', $request->token)->first();
        if (!$tokenData) {
            return ['message' => INVALID_TOKEN, 'success' => false];
        }
        $user = $this->repository->findByField('email', $request->email);
        if (count($user) < 1) {
            return ['message' => NOT_FOUND, 'success' => false];
        }
        return ['message' => TOKEN_IS_VALID, 'success' => true];
    }
    /**
     * @OA\Post(
     *     path="/reset-password",
     *     operationId="resetPassword",
     *     tags={"Password Management"},
     *     summary="Reset Password",
     *     description="Reset user password using token from email",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "token", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="token", type="string", example="abc123xyz"),
     *             @OA\Property(property="password", type="string", format="password", example="newSecurePassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successful",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string',
                'email' => 'email|required',
                'token' => 'required|string'
            ]);

            $user = $this->repository->where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            DB::table('password_resets')->where('email', $user->email)->delete();

            return ['message' => PASSWORD_RESET_SUCCESSFUL, 'success' => true];
        } catch (\Exception $th) {
            return ['message' => SOMETHING_WENT_WRONG, 'success' => false];
        }
    }

    /**
     * @OA\Post(
     *     path="/change-password",
     *     operationId="changePassword",
     *     tags={"Password Management"},
     *     summary="Change Password",
     *     description="Change the current user's password (requires old password)",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"oldPassword", "newPassword"},
     *             @OA\Property(property="oldPassword", type="string", format="password", example="currentPassword123"),
     *             @OA\Property(property="newPassword", type="string", format="password", example="newSecurePassword456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error or old password incorrect")
     * )
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = $request->user();
            if (Hash::check($request->oldPassword, $user->password)) {
                $user->password = Hash::make($request->newPassword);
                $user->save();
                return ['message' => PASSWORD_RESET_SUCCESSFUL, 'success' => true];
            } else {
                return ['message' => OLD_PASSWORD_INCORRECT, 'success' => false];
            }
        } catch (\Exception $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
    public function contactAdmin(Request $request)
    {
        try {
            $listedAdmin = [];
            $admins = $this->getAdminUsers();
            if (isset($admins)) {
                foreach ($admins as $key => $admin) {
                    array_push($listedAdmin, $admin->email);
                }
            }
            $details = $request->only('subject', 'name', 'email', 'description');
            // config('shop.admin_email')
            $emailTo = isset($request['emailTo']) ? $request['emailTo'] : $listedAdmin;
            Mail::to($emailTo)->send(new ContactAdmin($details));
            return ['message' => EMAIL_SENT_SUCCESSFUL, 'success' => true];
        } catch (\Exception $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function fetchStaff(Request $request)
    {
        try {
            if (!isset($request->shop_id)) {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }
            if ($this->repository->hasPermission($request->user(), $request->shop_id)) {
                return $this->repository->with(['profile'])->where('shop_id', '=', $request->shop_id);
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function staffs(Request $request)
    {
        $query = $this->fetchStaff($request);
        $limit = $request->limit ?? 15;
        return $query->paginate($limit);
    }

    /**
     * @OA\Post(
     *     path="/social-login-token",
     *     operationId="socialLogin",
     *     tags={"Authentication"},
     *     summary="Social Login",
     *     description="Login or register using Facebook or Google OAuth token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"provider", "access_token"},
     *             @OA\Property(property="provider", type="string", enum={"facebook", "google"}, example="google"),
     *             @OA\Property(property="access_token", type="string", example="ya29.a0AfH6SMC...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully authenticated",
     *         @OA\JsonContent(ref="#/components/schemas/AuthResponse")
     *     ),
     *     @OA\Response(response=422, description="Invalid provider or token")
     * )
     */
    public function socialLogin(Request $request)
    {
        $provider = $request->provider;
        $token = $request->access_token;
        $this->validateProvider($provider);

        try {
            $user = Socialite::driver($provider)->userFromToken($token);
            $userExist = User::where('email', $user->email)->exists();

            $userCreated = User::firstOrCreate(
                [
                    'email' => $user->getEmail()
                ],
                [
                    'email_verified_at' => now(),
                    'name' => $user->getName(),
                ]
            );

            $userCreated->providers()->updateOrCreate(
                [
                    'provider' => $provider,
                    'provider_user_id' => $user->getId(),
                ]
            );

            $avatar = [
                'thumbnail' => $user->getAvatar(),
                'original' => $user->getAvatar(),
            ];

            $userCreated->profile()->updateOrCreate(
                [
                    'avatar' => $avatar
                ]
            );

            if (!$userCreated->hasPermissionTo(Permission::CUSTOMER)) {
                $userCreated->givePermissionTo(Permission::CUSTOMER);
                $userCreated->assignRole(Role::CUSTOMER);
            }

            if (empty($userExist)) {
                $this->giveSignupPointsToCustomer($userCreated->id);
            }
            event(new ProcessUserData());
            return [
                "token" => $userCreated->createToken('auth_token')->plainTextToken,
                "permissions" => $userCreated->getPermissionNames(),
                "role" => $userCreated->getRoleNames()->first()
            ];
        } catch (\Exception $e) {
            throw new MarvelException(INVALID_CREDENTIALS);
        }
    }

    protected function validateProvider($provider)
    {
        if (!in_array($provider, ['facebook', 'google'])) {
            throw new MarvelException(PLEASE_LOGIN_USING_FACEBOOK_OR_GOOGLE);
        }
    }

    protected function getOtpGateway()
    {
        $gateway = config('auth.active_otp_gateway');
        $gateWayClass = "Marvel\\Otp\\Gateways\\" . ucfirst($gateway) . 'Gateway';
        return new OtpGateway(new $gateWayClass());
    }

    protected function verifyOtp(Request $request)
    {
        $id = $request->otp_id;
        $code = $request->code;
        $phoneNumber = $request->phone_number;
        try {
            $otpGateway = $this->getOtpGateway();
            $verifyOtpCode = $otpGateway->checkVerification($id, $code, $phoneNumber);
            if ($verifyOtpCode->isValid()) {
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Send OTP Code - ROUTE DISABLED
     * Uncomment routes in Routes.php to enable
     */
    public function sendOtpCode(Request $request)
    {
        $phoneNumber = $request->phone_number;
        try {
            if (empty($phoneNumber)) {
                return ['message' => config('shop.app_notice_domain') . 'ERROR.EMPTY_MOBILE_NUMBER', 'success' => false];
            }

            $otpGateway = $this->getOtpGateway();
            $sendOtpCode = $otpGateway->startVerification($phoneNumber);
            if (!$sendOtpCode->isValid()) {
                return ['message' => OTP_SEND_FAIL, 'success' => false];
            }
            $profile = Profile::where('contact', $phoneNumber)->first();
            return [
                'message' => OTP_SEND_SUCCESSFUL,
                'success' => true,
                'provider' => config('auth.active_otp_gateway'),
                'id' => $sendOtpCode->getId(),
                'phone_number' => $phoneNumber,
                'is_contact_exist' => $profile ? true : false
            ];
        } catch (MarvelException $e) {
            throw new MarvelException(INVALID_GATEWAY);
        }
    }

    /**
     * Verify OTP Code - ROUTE DISABLED
     * Uncomment routes in Routes.php to enable
     */
    public function verifyOtpCode(Request $request)
    {
        try {
            if ($this->verifyOtp($request)) {
                return [
                    "message" => OTP_SEND_SUCCESSFUL,
                    "success" => true,
                ];
            }
            throw new MarvelException(OTP_VERIFICATION_FAILED);
        } catch (\Throwable $e) {
            throw new MarvelException(OTP_VERIFICATION_FAILED);
        }
    }

    /**
     * Login via OTP - ROUTE DISABLED
     * Uncomment routes in Routes.php to enable
     */
    public function otpLogin(Request $request)
    {
        $phoneNumber = $request->phone_number;

        try {
            if ($this->verifyOtp($request)) {
                // check if phone number exist
                $profile = Profile::where('contact', $phoneNumber)->first();
                $user = '';
                if (!$profile) {
                    // profile not found so could be a new user
                    $name = $request->name;
                    $email = $request->email;
                    if ($name && $email) {
                        $userExist = User::where('email', $email)->exists();
                        $user = User::firstOrCreate(
                            [
                                'email' => $email,
                            ],
                            [
                                'name' => $name,
                                // Mark phone-based signups as verified by default
                                'email_verified_at' => now(),
                            ]
                        );
                        $user->givePermissionTo(Permission::CUSTOMER);
                        $user->assignRole(Role::CUSTOMER);

                        $user->profile()->updateOrCreate(
                            ['customer_id' => $user->id],
                            [
                                'contact' => $phoneNumber
                            ]
                        );
                        if (empty($userExist)) {
                            $this->giveSignupPointsToCustomer($user->id);
                        }
                    } else {
                        return ['message' => REQUIRED_INFO_MISSING, 'success' => false];
                    }
                } else {
                    $user = User::where('id', $profile->customer_id)->first();
                }
                event(new ProcessUserData());
                return [
                    "token" => $user->createToken('auth_token')->plainTextToken,
                    "permissions" => $user->getPermissionNames(),
                    "role" => $user->getRoleNames()->first()
                ];
            }
            return ['message' => OTP_VERIFICATION_FAILED, 'success' => false];
        } catch (\Throwable $e) {
            return response()->json(['error' => INVALID_GATEWAY], 422);
        }
    }

    public function updateContact(Request $request)
    {
        $phoneNumber = $request->phone_number;
        $user_id = $request->user_id;

        try {
            if ($this->verifyOtp($request)) {
                $user = User::find($user_id);
                $user->profile()->updateOrCreate(
                    ['customer_id' => $user_id],
                    [
                        'contact' => $phoneNumber
                    ]
                );
                return [
                    "message" => CONTACT_UPDATE_SUCCESSFUL,
                    "success" => true,
                ];
            }
            return ['message' => CONTACT_UPDATE_FAILED, 'success' => false];
        } catch (\Exception $e) {
            return response()->json(['error' => INVALID_GATEWAY], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/add-points",
     *     operationId="addPoints",
     *     tags={"User Management"},
     *     summary="Add Loyalty Points",
     *     description="Add loyalty/reward points to a customer's wallet. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "points"},
     *             @OA\Property(property="customer_id", type="integer", example=5, description="User ID to add points to"),
     *             @OA\Property(property="points", type="number", example=100, description="Number of points to add")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Points added successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function addPoints(Request $request)
    {
        $request->validate([
            'points' => 'required|numeric',
            'customer_id' => ['required', 'exists:Marvel\Database\Models\User,id']
        ]);
        $points = $request->points;
        $customer_id = $request->customer_id;

        $wallet = Wallet::firstOrCreate(['customer_id' => $customer_id]);
        $wallet->total_points = $wallet->total_points + $points;
        $wallet->available_points = $wallet->available_points + $points;
        $wallet->save();
        return ['message' => 'Points added successfully', 'success' => true];
    }

    /**
     * @OA\Post(
     *     path="/users/make-admin",
     *     operationId="makeOrRevokeAdmin",
     *     tags={"User Management"},
     *     summary="Toggle Admin Status",
     *     description="Grant or revoke SUPER_ADMIN permission from a user. If user is admin, revokes; if not, grants. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="integer", example=5, description="User ID to toggle admin status")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Admin status toggled successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function makeOrRevokeAdmin(Request $request)
    {
        $user = $request->user();
        if ($this->repository->hasPermission($user)) {
            $user_id = $request->user_id;
            try {
                $newUser = $this->repository->findOrFail($user_id);
                if ($newUser->hasPermissionTo(Permission::SUPER_ADMIN)) {
                    $newUser->revokePermissionTo(Permission::SUPER_ADMIN);
                    $newUser->removeRole(Role::SUPER_ADMIN);
                    return true;
                }
            } catch (Exception $e) {
                throw new MarvelException(USER_NOT_FOUND);
            }
            $newUser->givePermissionTo(Permission::SUPER_ADMIN);
            $newUser->assignRole(Role::SUPER_ADMIN);

            return true;
        }

        throw new MarvelException(NOT_AUTHORIZED);
    }
    /**
     * @OA\Post(
     *     path="/subscribe-to-newsletter",
     *     operationId="subscribeNewsletter",
     *     tags={"Users"},
     *     summary="Subscribe to newsletter",
     *     description="Add an email address to the platform's newsletter subscription list.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="buyer@example.com")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully subscribed", @OA\JsonContent(type="boolean", example=true)),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Something went wrong")
     * )
     */
    public function subscribeToNewsletter(Request $request)
    {
        try {
            $email = $request->email;
            Newsletter::subscribeOrUpdate($email);
            return true;
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
    /**
     * @OA\Post(
     *     path="/update-email",
     *     operationId="updateEmail",
     *     tags={"Authentication"},
     *     summary="Update User Email",
     *     description="Update the current user's email address. Requires authentication.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="newemail@example.com")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Email updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateUserEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
        ]);
        if ($validator->fails()) {
            throw new MarvelException($validator->errors()->first());
        }
        return $this->repository->updateEmail($request);
    }

    public function myStaffs(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;
        return $this->fetchMyStaffs($request)->paginate($limit);
    }
    public function fetchMyStaffs(Request $request)
    {
        $user = $request->user();
        if ($this->repository->hasPermission($user, $request->shop_id)) {
            return $this->repository->whereRelation('managed_shop', 'owner_id', '=', $user->id);
        }
        return $this->repository->whereRelation('managed_shop', 'owner_id', '=', null);
    }

    public function allStaffs(Request $request)
    {
        $user = $request->user();
        $limit = $request->limit ? $request->limit : 15;
        if ($this->repository->hasPermission($user)) {
            return $this->repository->permission(Permission::STAFF)->paginate($limit);
        }
        return $this->repository->permission(null)->paginate($limit);
    }


    public function verifyLicenseKey(LicenseRequest $request, MarvelVerification $verification)
    {
        try {
            $licenseKey = $request->license_key;
            $language = $request['language'] ?? DEFAULT_LANGUAGE;
            $marvel = $verification->verify($licenseKey);
            if (!$marvel->getTrust()) {
                throw new MarvelNotFoundException(INVALID_LICENSE_KEY);
            }
            return $marvel->modifySettingsData($language);
        } catch (MarvelException $th) {
            throw new MarvelException(INVALID_LICENSE_KEY);
        }
    }
    public function fetchUsersByPermission(Request $request)
    {
        $user = $request->user() ?? null;
        $permission = strtolower($request->permission) ?? true;
        $is_active = $request->is_active ?? true;
        $query = $this->repository->where('is_active', $is_active);
        if (!$this->repository->hasPermission($user, $request->shop_id)) {
            return $query->permission(null);
        }
        switch ($permission) {
            case Permission::SUPER_ADMIN:
                $query->permission($permission);
                break;
            case Permission::STORE_OWNER:
                $excludeUsers = User::permission(Permission::SUPER_ADMIN)->pluck('id')->toArray();
                if (isset($request->exclude)) {
                    $excludeUsers = [...$excludeUsers, $request->exclude];
                }
                $query->permission($permission)->whereNotIn('id', $excludeUsers);
                break;
            case Permission::STAFF:
                $query->permission($permission);
                break;
            case Permission::CUSTOMER:
                $excludeUsers = User::permission([Permission::SUPER_ADMIN, Permission::STORE_OWNER, Permission::STAFF])
                    ->pluck('id')->toArray();
                $query->permission($permission)->whereNotIn('id', $excludeUsers);
                break;
            default:
                $query->permission(null);
                break;
        }
        return $query;
    }
}
