<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ActivityHeartbeatController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\IptvController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostImageController;
use App\Http\Controllers\RadioController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\UserBlockController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:600,1'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['auth:sanctum', 'throttle:600,1'])->get('/media/avatars/{user}', [MediaController::class, 'showAvatar'])->name('media.avatars.show');

Route::post('/feedback', [FeedbackController::class, 'store'])
    ->middleware('throttle:20,1');
Route::get('/site/home-content', [SiteSettingController::class, 'homeContent'])
    ->middleware('throttle:240,1');
Route::get('/site/world-overview', [SiteSettingController::class, 'worldOverview'])
    ->middleware('throttle:240,1');
Route::get('/media/post-images/{postImage}', [MediaController::class, 'showPostImage'])
    ->middleware('throttle:600,1')
    ->name('media.post-images.show');
Route::get('/iptv/transcode/{session}/playlist.m3u8', [IptvController::class, 'transcodePlaylist'])
    ->middleware('throttle:1200,1')
    ->name('api.iptv.transcode.playlist');
Route::get('/iptv/transcode/{session}/{segment}', [IptvController::class, 'transcodeSegment'])
    ->middleware('throttle:1200,1')
    ->where('segment', 'segment_[0-9]{5}\.ts')
    ->name('api.iptv.transcode.segment');
Route::get('/iptv/relay/{session}/playlist.m3u8', [IptvController::class, 'relayPlaylist'])
    ->middleware('throttle:1200,1')
    ->name('api.iptv.relay.playlist');
Route::get('/iptv/relay/{session}/{segment}', [IptvController::class, 'relaySegment'])
    ->middleware('throttle:1200,1')
    ->where('segment', 'segment_[0-9]{5}\.ts')
    ->name('api.iptv.relay.segment');
Route::get('/iptv/proxy/{session}/playlist.m3u8', [IptvController::class, 'proxyPlaylist'])
    ->middleware('throttle:1200,1')
    ->name('api.iptv.proxy.playlist');
Route::get('/iptv/proxy/{session}/segment', [IptvController::class, 'proxySegment'])
    ->middleware('throttle:1200,1')
    ->name('api.iptv.proxy.segment');

Route::middleware(['auth:sanctum', 'throttle:6,1'])->post('/auth/email/verification-notification', EmailVerificationNotificationController::class);

Route::middleware(['auth:sanctum', 'verified', 'throttle:600,1'])->group(function () {
    Route::get('/feedback/my', [FeedbackController::class, 'my']);
    Route::post('/activity/heartbeat', [ActivityHeartbeatController::class, 'store'])
        ->middleware('throttle:120,1');

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}/posts', [UserController::class, 'post']);
    Route::post('/users/profile', [UserController::class, 'updateProfile']);
    Route::post('/users/{user}/toggle_following', [UserController::class, 'toggleFollowing']);
    Route::get('/users/blocks', [UserBlockController::class, 'index']);
    Route::post('/users/{user}/block', [UserBlockController::class, 'store']);
    Route::delete('/users/{user}/block', [UserBlockController::class, 'destroy']);
    Route::get('/users/following_posts', [UserController::class, 'followingPost']);
    Route::post('/users/stats', [UserController::class, 'stat']);

    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/discover', [PostController::class, 'discover']);
    Route::get('/posts/carousel', [PostController::class, 'carousel']);
    Route::post('/posts/{post}/view', [PostController::class, 'markViewed']);
    Route::post('/posts/{post}/toggle_like', [PostController::class, 'toggleLike']);
    Route::delete('/posts/{post}/like', [PostController::class, 'removeLike']);
    Route::post('/posts/{post}/repost', [PostController::class, 'repost']);
    Route::post('/posts/{post}/comment', [PostController::class, 'comment']);
    Route::get('/posts/{post}/comment', [PostController::class, 'commentList']);
    Route::delete('/posts/{post}/comments/{comment}', [PostController::class, 'destroyComment']);
    Route::delete('/posts/{post}/media/{postImage}', [PostController::class, 'destroyMedia']);

    Route::post('/post_media', [PostImageController::class, 'store']);
    Route::post('/post_images', [PostImageController::class, 'store']);
    Route::get('/media/chat-attachments/{attachment}', [MediaController::class, 'showChatAttachment'])->name('media.chat-attachments.show');
    Route::get('/media/chat-attachments/{attachment}/download', [MediaController::class, 'downloadChatAttachment'])->name('media.chat-attachments.download');

    Route::get('/site/config', [SiteSettingController::class, 'publicConfig']);
    Route::patch('/site/storage-preference', [SiteSettingController::class, 'updateUserStoragePreference']);

    Route::get('/iptv/seeds', [IptvController::class, 'seeds']);
    Route::get('/radio/stations', [RadioController::class, 'stations']);
    Route::get('/radio/stream', [RadioController::class, 'stream'])->name('api.radio.stream');
    Route::get('/radio/favorites', [RadioController::class, 'favorites']);
    Route::post('/radio/favorites', [RadioController::class, 'storeFavorite']);
    Route::delete('/radio/favorites/{stationUuid}', [RadioController::class, 'destroyFavorite']);
    Route::post('/iptv/playlist/fetch', [IptvController::class, 'fetchPlaylist']);
    Route::get('/iptv/saved', [IptvController::class, 'savedLibrary']);
    Route::post('/iptv/saved/playlists', [IptvController::class, 'storeSavedPlaylist']);
    Route::patch('/iptv/saved/playlists/{playlistId}', [IptvController::class, 'updateSavedPlaylist'])
        ->whereNumber('playlistId');
    Route::delete('/iptv/saved/playlists/{playlistId}', [IptvController::class, 'destroySavedPlaylist'])
        ->whereNumber('playlistId');
    Route::post('/iptv/saved/channels', [IptvController::class, 'storeSavedChannel']);
    Route::patch('/iptv/saved/channels/{channelId}', [IptvController::class, 'updateSavedChannel'])
        ->whereNumber('channelId');
    Route::delete('/iptv/saved/channels/{channelId}', [IptvController::class, 'destroySavedChannel'])
        ->whereNumber('channelId');
    Route::post('/iptv/proxy/start', [IptvController::class, 'startProxy'])->name('api.iptv.proxy.start');
    Route::delete('/iptv/proxy/{session}', [IptvController::class, 'stopProxy'])->name('api.iptv.proxy.stop');
    Route::get('/iptv/transcode/capabilities', [IptvController::class, 'transcodeCapabilities'])->name('api.iptv.transcode.capabilities');
    Route::post('/iptv/transcode/start', [IptvController::class, 'startTranscode'])->name('api.iptv.transcode.start');
    Route::delete('/iptv/transcode/{session}', [IptvController::class, 'stopTranscode'])->name('api.iptv.transcode.stop');
    Route::post('/iptv/relay/start', [IptvController::class, 'startRelay'])->name('api.iptv.relay.start');
    Route::delete('/iptv/relay/{session}', [IptvController::class, 'stopRelay'])->name('api.iptv.relay.stop');

    Route::get('/chats', [ChatController::class, 'index']);
    Route::get('/chats/unread-summary', [ChatController::class, 'unreadSummary']);
    Route::get('/chats/users', [ChatController::class, 'users']);
    Route::get('/chats/settings', [ChatController::class, 'settings']);
    Route::patch('/chats/settings', [ChatController::class, 'updateSettings']);
    Route::get('/chats/archives', [ChatController::class, 'archives']);
    Route::post('/chats/archives', [ChatController::class, 'createArchive']);
    Route::get('/chats/archives/{archive}/download', [ChatController::class, 'downloadArchive']);
    Route::post('/chats/archives/{archive}/restore', [ChatController::class, 'restoreArchive']);
    Route::post('/chats/direct/{user}', [ChatController::class, 'createOrGetDirect']);
    Route::get('/chats/{conversation}', [ChatController::class, 'show']);
    Route::post('/chats/{conversation}/read', [ChatController::class, 'markRead']);
    Route::patch('/chats/{conversation}/mood-status', [ChatController::class, 'upsertMoodStatus']);
    Route::get('/chats/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('/chats/{conversation}/messages', [ChatController::class, 'storeMessage']);
    Route::post('/chats/{conversation}/messages/{message}/reactions', [ChatController::class, 'toggleMessageReaction']);
    Route::delete('/chats/{conversation}/messages/{message}', [ChatController::class, 'destroyMessage']);
    Route::delete('/chats/{conversation}/messages/{message}/attachments/{attachment}', [ChatController::class, 'destroyMessageAttachment']);

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/summary', [AdminController::class, 'summary']);
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/dashboard/export', [AdminController::class, 'exportDashboard']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser']);

        Route::get('/posts', [AdminController::class, 'posts']);
        Route::post('/posts', [AdminController::class, 'storePost']);
        Route::patch('/posts/{post}', [AdminController::class, 'updatePost']);
        Route::delete('/posts/{post}', [AdminController::class, 'destroyPost']);
        Route::delete('/posts/{post}/likes', [AdminController::class, 'clearPostLikes']);
        Route::delete('/likes', [AdminController::class, 'clearAllLikes']);

        Route::get('/comments', [AdminController::class, 'comments']);
        Route::delete('/comments/{comment}', [AdminController::class, 'destroyComment']);

        Route::get('/feedback', [AdminController::class, 'feedback']);
        Route::patch('/feedback/{feedback}', [AdminController::class, 'updateFeedback']);
        Route::delete('/feedback/{feedback}', [AdminController::class, 'destroyFeedback']);

        Route::get('/conversations', [AdminController::class, 'conversations']);
        Route::delete('/conversations/messages', [AdminController::class, 'clearAllConversationMessages']);
        Route::delete('/conversations', [AdminController::class, 'clearAllConversations']);
        Route::delete('/conversations/{conversation}/messages', [AdminController::class, 'clearConversationMessages']);
        Route::delete('/conversations/{conversation}', [AdminController::class, 'destroyConversation']);
        Route::get('/messages', [AdminController::class, 'messages']);
        Route::delete('/messages/{message}', [AdminController::class, 'destroyMessage']);

        Route::get('/blocks', [AdminController::class, 'blocks']);
        Route::patch('/blocks/{userBlock}', [AdminController::class, 'updateBlock']);
        Route::delete('/blocks/{userBlock}', [AdminController::class, 'destroyBlock']);

        Route::get('/iptv-seeds', [AdminController::class, 'iptvSeeds']);
        Route::post('/iptv-seeds', [AdminController::class, 'storeIptvSeed']);
        Route::patch('/iptv-seeds/{iptvSeed}', [AdminController::class, 'updateIptvSeed']);
        Route::delete('/iptv-seeds/{iptvSeed}', [AdminController::class, 'destroyIptvSeed']);

        Route::get('/settings', [SiteSettingController::class, 'index']);
        Route::post('/settings', [SiteSettingController::class, 'store']);
        Route::patch('/settings/storage', [SiteSettingController::class, 'updateStorage']);
        Route::patch('/settings/home-content', [SiteSettingController::class, 'updateHomeContent']);
        Route::delete('/settings/home-content', [SiteSettingController::class, 'resetHomeContent']);
        Route::patch('/settings/{siteSetting}', [SiteSettingController::class, 'update']);
        Route::delete('/settings/{siteSetting}', [SiteSettingController::class, 'destroy']);
    });
});
