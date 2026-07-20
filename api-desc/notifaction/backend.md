# Backend - Notification Feature

## Controller - `packages/marvel/src/Http/Controllers/NotificationController.php`

Extends `App\Http\Controllers\Controller`. Uses `Marvel\Traits\ApiResponse`.

### Middleware Stack

```php
$this->middleware('auth:sanctum');
$this->middleware('admin');  // checks user->type === 'admin'
$this->middleware('permission:' . Permission::VIEW_NOTIFICATIONS)->only(['index', 'unread']);
$this->middleware('permission:' . Permission::MANAGE_NOTIFICATIONS)->except(['index', 'unread']);
```

### Methods

| Method | DB Query | Return |
|--------|----------|--------|
| `index()` | `$user->notifications()->latest()->paginate($perPage)` | Paginated list + meta |
| `unread()` | `$user->unreadNotifications()->latest()->get()` | All unread + count |
| `markAsRead($id)` | `$user->notifications()->findOrFail($id)->markAsRead()` | Single notification |
| `markAllAsRead()` | `$user->unreadNotifications()->update(['read_at' => now()])` | `{marked_count}` |
| `destroy($id)` | `$user->notifications()->findOrFail($id)->delete()` | `{}` |
| `destroyAll()` | `$user->notifications()->delete()` | `{deleted_count}` |

### formatNotification(DatabaseNotification): array

Extracts from `$notification->data` JSON:

| Field | Source | Default |
|-------|--------|---------|
| `id` | `$notification->id` | |
| `type` | `$notification->type` | |
| `title` | `$data['title']` | `''` |
| `message` | `$data['message']` | `''` |
| `icon` | `$data['icon']` | `'bell'` |
| `resource_type` | `$data['resource_type']` | `''` |
| `resource_id` | `$data['resource_id']` | `null` |
| `action_url` | `$data['action_url']` | `''` |
| `created_at` | `$notification->created_at?->toIso8601String()` | |
| `read_at` | `$notification->read_at?->toIso8601String()` | |

## AdminMiddleware - `app/Http/Middleware/AdminMiddleware.php`

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if (!$user || $user->type !== UserType::ADMIN->value) {
        abort(403, 'NOT_AUTHORIZED');
    }
    return $next($request);
}
```

## Event-Driven Notification Types

| Event | Notification Class | Title | resource_type |
|-------|-------------------|-------|---------------|
| `OrderCreated` | `NewOrderNotification` | "New Order" | order |
| `ContactMessageReceived` | `NewContactMessageNotification` | "New Contact Message" | contact |
| `AdminLoggedIn` | `AdminLoggedInNotification` | "Admin Login" | admin |

## Translations (MISSING)

All 6 constants are defined in `constants.php` but **missing** from both `resources/lang/en/message.php` and `resources/lang/ar/message.php`:

| Constant | Key |
|----------|-----|
| NOTIFICATIONS_FETCHED | MESSAGE.NOTIFICATIONS_FETCHED |
| UNREAD_NOTIFICATIONS_FETCHED | MESSAGE.UNREAD_NOTIFICATIONS_FETCHED |
| NOTIFICATION_MARKED_READ | MESSAGE.NOTIFICATION_MARKED_READ |
| ALL_NOTIFICATIONS_MARKED_READ | MESSAGE.ALL_NOTIFICATIONS_MARKED_READ |
| NOTIFICATION_DELETED | MESSAGE.NOTIFICATION_DELETED |
| ALL_NOTIFICATIONS_DELETED | MESSAGE.ALL_NOTIFICATIONS_DELETED |

## Permissions

| Permission Slug | Used On |
|----------------|---------|
| `view-notifications` | index, unread |
| `manage-notifications` | markAsRead, markAllAsRead, destroy, destroyAll |
