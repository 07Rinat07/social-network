<?php

namespace App\Http\Resources\Comment;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $name = isset($this->parent) ? $this->parent->user->name : null;
        return [
            'id' => $this->id,
            'body' => $this->body,
            'date' => $this->date,
            'answered_for_user' => $name,
            'user' => new UserResource($this->user),
        ];
    }
}
