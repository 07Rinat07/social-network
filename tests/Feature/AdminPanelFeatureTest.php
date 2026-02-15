<?php

namespace Tests\Feature;

use App\Events\FeedbackStatusUpdated;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\FeedbackMessage;
use App\Models\IptvSavedChannel;
use App\Models\IptvSavedPlaylist;
use App\Models\LikedPost;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\SubscriberFollowing;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_data_and_admin_flag(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/users/{$member->id}", [
            'name' => 'Updated Member',
            'email' => 'updated-member@example.com',
            'is_admin' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.name', 'Updated Member');

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'name' => 'Updated Member',
            'email' => 'updated-member@example.com',
            'is_admin' => true,
        ]);
    }

    public function test_admin_cannot_remove_own_admin_rights_and_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $removeRightsResponse = $this->patchJson("/api/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_admin' => false,
        ]);

        $removeRightsResponse
            ->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot remove admin rights from your own account.',
            ]);

        $deleteSelfResponse = $this->deleteJson("/api/admin/users/{$admin->id}");

        $deleteSelfResponse
            ->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot delete your own account from admin panel.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_update_and_delete_feedback_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $feedback = FeedbackMessage::query()->create([
            'name' => 'Reporter',
            'email' => 'reporter@example.com',
            'message' => 'Need moderation for abusive content.',
            'status' => FeedbackMessage::STATUS_NEW,
        ]);

        Sanctum::actingAs($admin);

        $updateResponse = $this->patchJson("/api/admin/feedback/{$feedback->id}", [
            'status' => FeedbackMessage::STATUS_IN_PROGRESS,
            'admin_note' => 'Taken into work.',
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.status', FeedbackMessage::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.admin_note', 'Taken into work.');

        $invalidStatusResponse = $this->patchJson("/api/admin/feedback/{$feedback->id}", [
            'status' => 'unknown',
        ]);

        $invalidStatusResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $deleteResponse = $this->deleteJson("/api/admin/feedback/{$feedback->id}");

        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('feedback_messages', ['id' => $feedback->id]);
    }

    public function test_admin_feedback_update_broadcasts_realtime_event_for_feedback_owner(): void
    {
        Event::fake([
            FeedbackStatusUpdated::class,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();

        $feedback = FeedbackMessage::query()->create([
            'user_id' => $owner->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'message' => 'Need an update about moderation result.',
            'status' => FeedbackMessage::STATUS_NEW,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/feedback/{$feedback->id}", [
            'status' => FeedbackMessage::STATUS_RESOLVED,
            'admin_note' => 'Issue resolved and content removed.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', FeedbackMessage::STATUS_RESOLVED)
            ->assertJsonPath('data.admin_note', 'Issue resolved and content removed.');

        Event::assertDispatched(FeedbackStatusUpdated::class, function (FeedbackStatusUpdated $event) use ($feedback, $owner) {
            return (int) $event->feedback->id === $feedback->id
                && (int) $event->feedback->user_id === $owner->id
                && $event->feedback->status === FeedbackMessage::STATUS_RESOLVED;
        });
    }

    public function test_admin_feedback_update_rejects_malicious_admin_note(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $feedback = FeedbackMessage::query()->create([
            'name' => 'Reporter',
            'email' => 'reporter@example.com',
            'message' => 'Need moderation for abusive content.',
            'status' => FeedbackMessage::STATUS_NEW,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/feedback/{$feedback->id}", [
            'status' => FeedbackMessage::STATUS_IN_PROGRESS,
            'admin_note' => '<script>alert("xss")</script>',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_note']);

        $this->assertDatabaseHas('feedback_messages', [
            'id' => $feedback->id,
            'status' => FeedbackMessage::STATUS_NEW,
            'admin_note' => null,
        ]);
    }

    public function test_admin_can_delete_comment_and_nullify_reply_parent_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Parent post',
            'content' => 'Parent post content',
            'user_id' => $author->id,
        ]);

        $parent = Comment::query()->create([
            'body' => 'Parent comment',
            'user_id' => $author->id,
            'post_id' => $post->id,
        ]);

        $reply = Comment::query()->create([
            'body' => 'Reply comment',
            'user_id' => $author->id,
            'post_id' => $post->id,
            'parent_id' => $parent->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/comments/{$parent->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('comments', ['id' => $parent->id]);
        $this->assertDatabaseHas('comments', [
            'id' => $reply->id,
            'parent_id' => null,
        ]);
    }

    public function test_admin_can_delete_post_and_cleanup_references_media_and_likes(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $originalPost = Post::query()->create([
            'title' => 'Original',
            'content' => 'Original content',
            'user_id' => $owner->id,
        ]);

        $otherPost = Post::query()->create([
            'title' => 'Another post',
            'content' => 'Another content',
            'user_id' => $other->id,
        ]);

        $repost = Post::query()->create([
            'title' => 'Repost',
            'content' => 'Repost content',
            'user_id' => $other->id,
            'reposted_id' => $originalPost->id,
        ]);

        LikedPost::query()->create([
            'user_id' => $other->id,
            'post_id' => $originalPost->id,
        ]);

        $parentComment = Comment::query()->create([
            'body' => 'Parent comment',
            'user_id' => $owner->id,
            'post_id' => $originalPost->id,
        ]);

        $crossPostReply = Comment::query()->create([
            'body' => 'Reply in another post',
            'user_id' => $other->id,
            'post_id' => $otherPost->id,
            'parent_id' => $parentComment->id,
        ]);

        Storage::disk('public')->put('media/images/admin-delete.jpg', 'content');

        PostImage::query()->create([
            'path' => 'media/images/admin-delete.jpg',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 123,
            'original_name' => 'admin-delete.jpg',
            'user_id' => $owner->id,
            'post_id' => $originalPost->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/posts/{$originalPost->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('posts', ['id' => $originalPost->id]);
        $this->assertDatabaseHas('posts', [
            'id' => $repost->id,
            'reposted_id' => null,
        ]);
        $this->assertDatabaseMissing('liked_posts', ['post_id' => $originalPost->id]);
        $this->assertDatabaseHas('comments', [
            'id' => $crossPostReply->id,
            'parent_id' => null,
        ]);
        $this->assertDatabaseMissing('post_images', [
            'post_id' => $originalPost->id,
        ]);

        Storage::disk('public')->assertMissing('media/images/admin-delete.jpg');
    }

    public function test_admin_can_create_and_update_posts_for_any_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $firstAuthor = User::factory()->create();
        $secondAuthor = User::factory()->create();

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/admin/posts', [
            'user_id' => $firstAuthor->id,
            'title' => 'Admin created post',
            'content' => "Created by admin for another author.\nWith multiline content.",
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => false,
        ]);

        $createResponse
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Admin created post')
            ->assertJsonPath('data.user.id', $firstAuthor->id)
            ->assertJsonPath('data.is_public', true);

        $createdPostId = (int) $createResponse->json('data.id');

        $this->assertDatabaseHas('posts', [
            'id' => $createdPostId,
            'user_id' => $firstAuthor->id,
            'title' => 'Admin created post',
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => false,
        ]);

        $updateResponse = $this->patchJson("/api/admin/posts/{$createdPostId}", [
            'user_id' => $secondAuthor->id,
            'title' => 'Admin updated post',
            'content' => 'Updated content by admin',
            'is_public' => false,
            'show_in_feed' => true,
            'show_in_carousel' => true,
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.id', $createdPostId)
            ->assertJsonPath('data.title', 'Admin updated post')
            ->assertJsonPath('data.user.id', $secondAuthor->id)
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.show_in_feed', false)
            ->assertJsonPath('data.show_in_carousel', false);

        $this->assertDatabaseHas('posts', [
            'id' => $createdPostId,
            'user_id' => $secondAuthor->id,
            'title' => 'Admin updated post',
            'content' => 'Updated content by admin',
            'is_public' => false,
            'show_in_feed' => false,
            'show_in_carousel' => false,
        ]);
    }

    public function test_admin_can_fetch_all_posts_for_full_moderation_scope(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create();

        for ($i = 1; $i <= 35; $i++) {
            Post::query()->create([
                'title' => "Post {$i}",
                'content' => "Content {$i}",
                'user_id' => $author->id,
            ]);
        }

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/posts?all=1');

        $response
            ->assertOk()
            ->assertJsonCount(35, 'data')
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links');
    }

    public function test_admin_can_clear_post_likes_and_all_likes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $thirdUser = User::factory()->create();

        $firstPost = Post::query()->create([
            'title' => 'First like target',
            'content' => 'First like target content',
            'user_id' => $firstUser->id,
        ]);

        $secondPost = Post::query()->create([
            'title' => 'Second like target',
            'content' => 'Second like target content',
            'user_id' => $secondUser->id,
        ]);

        LikedPost::query()->create([
            'user_id' => $secondUser->id,
            'post_id' => $firstPost->id,
        ]);
        LikedPost::query()->create([
            'user_id' => $thirdUser->id,
            'post_id' => $firstPost->id,
        ]);
        LikedPost::query()->create([
            'user_id' => $firstUser->id,
            'post_id' => $secondPost->id,
        ]);

        Sanctum::actingAs($admin);

        $clearPostLikesResponse = $this->deleteJson("/api/admin/posts/{$firstPost->id}/likes");

        $clearPostLikesResponse
            ->assertOk()
            ->assertJsonPath('data.post_id', $firstPost->id)
            ->assertJsonPath('data.removed_likes', 2);

        $this->assertDatabaseMissing('liked_posts', [
            'post_id' => $firstPost->id,
        ]);
        $this->assertDatabaseHas('liked_posts', [
            'post_id' => $secondPost->id,
        ]);

        $clearAllLikesResponse = $this->deleteJson('/api/admin/likes');

        $clearAllLikesResponse
            ->assertOk()
            ->assertJsonPath('data.removed_likes', 1);

        $this->assertDatabaseCount('liked_posts', 0);
    }

    public function test_admin_can_list_conversations_filter_messages_and_delete_message(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DIRECT,
            'created_by' => $admin->id,
        ]);
        $conversation->participants()->sync([$admin->id, $member->id]);

        $firstMessage = ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $admin->id,
            'body' => 'First admin message',
        ]);

        $attachmentPath = 'chat/images/admin-message-test.jpg';
        Storage::disk('public')->put($attachmentPath, 'attachment-content');

        ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $firstMessage->id,
            'path' => $attachmentPath,
            'type' => ConversationMessageAttachment::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 123,
            'original_name' => 'admin-message-test.jpg',
        ]);

        ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
            'body' => 'Member reply',
        ]);

        Sanctum::actingAs($admin);

        $conversationsResponse = $this->getJson('/api/admin/conversations');
        $messagesResponse = $this->getJson('/api/admin/messages?conversation_id=' . $conversation->id);

        $conversationsResponse
            ->assertOk()
            ->assertJsonPath('data.0.display_title', 'Личный чат');

        $messagesResponse
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $deleteResponse = $this->deleteJson("/api/admin/messages/{$firstMessage->id}");

        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('conversation_messages', ['id' => $firstMessage->id]);
        $this->assertDatabaseMissing('conversation_message_attachments', [
            'conversation_message_id' => $firstMessage->id,
        ]);
        Storage::disk('public')->assertMissing($attachmentPath);
    }

    public function test_admin_can_clear_messages_and_delete_all_conversations_with_attachment_cleanup(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();

        $firstConversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DIRECT,
            'created_by' => $admin->id,
        ]);
        $firstConversation->participants()->sync([$admin->id, $member->id]);

        $secondConversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DIRECT,
            'created_by' => $member->id,
        ]);
        $secondConversation->participants()->sync([$admin->id, $member->id]);

        $firstConversationMessage = ConversationMessage::query()->create([
            'conversation_id' => $firstConversation->id,
            'user_id' => $admin->id,
            'body' => 'First conversation message',
        ]);

        ConversationMessage::query()->create([
            'conversation_id' => $firstConversation->id,
            'user_id' => $member->id,
            'body' => 'Second first-conversation message',
        ]);

        $secondConversationMessage = ConversationMessage::query()->create([
            'conversation_id' => $secondConversation->id,
            'user_id' => $member->id,
            'body' => 'Second conversation message',
        ]);

        $firstAttachmentPath = 'chat/images/admin-clear-conversation.jpg';
        $secondAttachmentPath = 'chat/images/admin-clear-all-conversations.jpg';
        Storage::disk('public')->put($firstAttachmentPath, 'first-attachment');
        Storage::disk('public')->put($secondAttachmentPath, 'second-attachment');

        ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $firstConversationMessage->id,
            'path' => $firstAttachmentPath,
            'storage_disk' => 'public',
            'type' => ConversationMessageAttachment::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'original_name' => 'admin-clear-conversation.jpg',
        ]);

        ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $secondConversationMessage->id,
            'path' => $secondAttachmentPath,
            'storage_disk' => 'public',
            'type' => ConversationMessageAttachment::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'original_name' => 'admin-clear-all-conversations.jpg',
        ]);

        Sanctum::actingAs($admin);

        $clearConversationResponse = $this->deleteJson("/api/admin/conversations/{$firstConversation->id}/messages");

        $clearConversationResponse
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $firstConversation->id)
            ->assertJsonPath('data.removed_messages', 2)
            ->assertJsonPath('data.removed_attachments', 1);

        $this->assertDatabaseCount('conversation_messages', 1);
        $this->assertDatabaseHas('conversations', ['id' => $firstConversation->id]);
        Storage::disk('public')->assertMissing($firstAttachmentPath);
        Storage::disk('public')->assertExists($secondAttachmentPath);

        $clearAllMessagesResponse = $this->deleteJson('/api/admin/conversations/messages');

        $clearAllMessagesResponse
            ->assertOk()
            ->assertJsonPath('data.removed_messages', 1)
            ->assertJsonPath('data.removed_attachments', 1);

        $this->assertDatabaseCount('conversation_messages', 0);
        $this->assertDatabaseCount('conversations', 2);
        Storage::disk('public')->assertMissing($secondAttachmentPath);

        $deleteAllConversationsResponse = $this->deleteJson('/api/admin/conversations');

        $deleteAllConversationsResponse
            ->assertOk()
            ->assertJsonPath('data.removed_conversations', 2)
            ->assertJsonPath('data.removed_attachments', 0);

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_admin_can_list_update_and_delete_user_blocks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();

        $block = UserBlock::query()->create([
            'blocker_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
            'expires_at' => now()->addHours(10),
            'reason' => 'Initial reason',
        ]);

        $originalExpiresTimestamp = $block->expires_at?->timestamp;

        Sanctum::actingAs($admin);

        $listResponse = $this->getJson('/api/admin/blocks');

        $listResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $block->id);

        $updateReasonResponse = $this->patchJson("/api/admin/blocks/{$block->id}", [
            'reason' => 'Updated reason only',
        ]);

        $updateReasonResponse
            ->assertOk()
            ->assertJsonPath('data.reason', 'Updated reason only');

        $block->refresh();
        $this->assertSame($originalExpiresTimestamp, $block->expires_at?->timestamp);

        $newExpiry = now()->addDays(2);
        $updateExpiryResponse = $this->patchJson("/api/admin/blocks/{$block->id}", [
            'expires_at' => $newExpiry->toIso8601String(),
            'reason' => 'Extended',
        ]);

        $updateExpiryResponse
            ->assertOk()
            ->assertJsonPath('data.reason', 'Extended');

        $block->refresh();
        $this->assertSame($newExpiry->timestamp, $block->expires_at?->timestamp);

        $deleteResponse = $this->deleteJson("/api/admin/blocks/{$block->id}");

        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('user_blocks', [
            'id' => $block->id,
        ]);
    }

    public function test_admin_can_delete_user_and_cleanup_related_data(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true]);
        $victim = User::factory()->create();
        $other = User::factory()->create();

        $victimPost = Post::query()->create([
            'title' => 'Victim post',
            'content' => 'Will be removed',
            'user_id' => $victim->id,
        ]);

        $repost = Post::query()->create([
            'title' => 'Reference post',
            'content' => 'References victim post',
            'user_id' => $other->id,
            'reposted_id' => $victimPost->id,
        ]);

        $victimComment = Comment::query()->create([
            'body' => 'Victim comment',
            'user_id' => $victim->id,
            'post_id' => $victimPost->id,
        ]);

        Comment::query()->create([
            'body' => 'Child comment',
            'user_id' => $other->id,
            'post_id' => $repost->id,
            'parent_id' => $victimComment->id,
        ]);

        LikedPost::query()->create([
            'user_id' => $victim->id,
            'post_id' => $repost->id,
        ]);

        SubscriberFollowing::query()->create([
            'subscriber_id' => $victim->id,
            'following_id' => $other->id,
        ]);

        SubscriberFollowing::query()->create([
            'subscriber_id' => $other->id,
            'following_id' => $victim->id,
        ]);

        Storage::disk('public')->put('media/images/victim-post.jpg', 'image-content');
        Storage::disk('public')->put('media/images/victim-orphan.jpg', 'orphan-content');

        PostImage::query()->create([
            'path' => 'media/images/victim-post.jpg',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 300,
            'original_name' => 'victim-post.jpg',
            'user_id' => $victim->id,
            'post_id' => $victimPost->id,
        ]);

        PostImage::query()->create([
            'path' => 'media/images/victim-orphan.jpg',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 200,
            'original_name' => 'victim-orphan.jpg',
            'user_id' => $victim->id,
            'post_id' => null,
        ]);

        $feedback = FeedbackMessage::query()->create([
            'user_id' => $victim->id,
            'name' => 'Victim',
            'email' => 'victim@example.com',
            'message' => 'Feedback entry',
            'status' => FeedbackMessage::STATUS_NEW,
        ]);

        $directConversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DIRECT,
            'created_by' => $victim->id,
        ]);
        $directConversation->participants()->sync([$victim->id, $other->id]);

        ConversationMessage::query()->create([
            'conversation_id' => $directConversation->id,
            'user_id' => $victim->id,
            'body' => 'Victim message',
        ]);

        IptvSavedPlaylist::query()->create([
            'user_id' => $victim->id,
            'name' => 'Victim IPTV list',
            'source_url' => 'https://iptv.example.com/victim-list.m3u',
            'source_url_hash' => hash('sha256', 'https://iptv.example.com/victim-list.m3u'),
            'channels_count' => 55,
        ]);

        IptvSavedChannel::query()->create([
            'user_id' => $victim->id,
            'name' => 'Victim IPTV channel',
            'stream_url' => 'https://stream.example.com/victim.m3u8',
            'stream_url_hash' => hash('sha256', 'https://stream.example.com/victim.m3u8'),
            'group_title' => 'General',
            'logo_url' => 'https://img.example.com/victim.png',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/users/{$victim->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
        $this->assertDatabaseMissing('posts', ['id' => $victimPost->id]);
        $this->assertDatabaseHas('posts', [
            'id' => $repost->id,
            'reposted_id' => null,
        ]);
        $this->assertDatabaseMissing('post_images', ['user_id' => $victim->id]);
        $this->assertDatabaseMissing('liked_posts', ['user_id' => $victim->id]);
        $this->assertDatabaseMissing('subscriber_followings', ['subscriber_id' => $victim->id]);
        $this->assertDatabaseMissing('subscriber_followings', ['following_id' => $victim->id]);
        $this->assertDatabaseMissing('conversation_messages', ['user_id' => $victim->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $directConversation->id]);
        $this->assertDatabaseMissing('iptv_saved_playlists', ['user_id' => $victim->id]);
        $this->assertDatabaseMissing('iptv_saved_channels', ['user_id' => $victim->id]);
        $this->assertDatabaseHas('feedback_messages', [
            'id' => $feedback->id,
            'user_id' => null,
        ]);

        Storage::disk('public')->assertMissing('media/images/victim-post.jpg');
        Storage::disk('public')->assertMissing('media/images/victim-orphan.jpg');
    }
}
