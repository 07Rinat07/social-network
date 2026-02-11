<?php

namespace App\Http\Resources\Post;

use App\Http\Resources\PostImage\PostImageResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $media = $this->relationLoaded('media') ? $this->media : collect();
        $firstImage = $media->first(fn ($item) => $item->type === 'image');
        $url = $firstImage?->url;

        $likesCount = $this->liked_users_count;
        if ($likesCount === null && $this->relationLoaded('likedUsers')) {
            $likesCount = $this->likedUsers->count();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'image_url' => $url,
            'media' => PostImageResource::collection($this->whenLoaded('media')),
            'user' => new UserResource($this->whenLoaded('user')),
            'date' => $this->date,
            'is_liked' => (bool) ($this->is_liked ?? false),
            'likes_count' => (int) ($likesCount ?? 0),
            'views_count' => (int) ($this->views_count ?? 0),
            'is_public' => (bool) ($this->is_public ?? false),
            'show_in_feed' => (bool) ($this->show_in_feed ?? false),
            'show_in_carousel' => (bool) ($this->show_in_carousel ?? false),
            'reposted_post' => $this->relationLoaded('repostedPost') && $this->repostedPost
                ? new RepostedPostResource($this->repostedPost)
                : null,
            'comments_count' => (int) ($this->comments_count ?? 0),
            'reposted_by_posts_count' => (int) ($this->reposted_by_posts_count ?? 0),
        ];
    }
}
