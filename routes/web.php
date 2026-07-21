<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-pusher', function () {
    $user = \Marvel\Database\Models\User::where('type', 'admin')->first();

    if (!$user) {
        return response()->json(['error' => 'No admin user found'], 404);
    }

    try {
        broadcast(new \App\Events\AdminLoggedIn($user, request()->ip(), request()->userAgent()));
        $result = 'Broadcast dispatched';
    } catch (\Exception $e) {
        $result = 'Broadcast ERROR: ' . $e->getMessage();
    }

    $pusher = new \Pusher\Pusher(
        config('broadcasting.connections.pusher.key'),
        config('broadcasting.connections.pusher.secret'),
        config('broadcasting.connections.pusher.app_id'),
        config('broadcasting.connections.pusher.options')
    );

    try {
        $directResult = $pusher->trigger('private-admin.notifications', 'admin.logged.in', [
            'name' => $user->name,
            'email' => $user->email,
        ]);
    } catch (\Exception $e) {
        $directResult = 'Direct ERROR: ' . $e->getMessage();
    }

    return response()->json([
        'success' => true,
        'broadcast_result' => $result,
        'direct_pusher_result' => $directResult,
        'event' => 'admin.logged.in',
        'channel' => 'private-admin.notifications',
        'auth_endpoint_for_frontend' => url('/api/v1/broadcasting/auth'),
        'pusher_key' => config('broadcasting.connections.pusher.key'),
        'pusher_cluster' => config('broadcasting.connections.pusher.options.cluster'),
    ]);
});
