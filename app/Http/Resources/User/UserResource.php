<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'is_admin' => (bool) $this->is_admin,
            'email' => $this->when(
                $request->user() && $request->user()->id === $this->id,
                $this->email
            ),
            'is_followed' => $this->is_followed ?? false,
            'media_storage_preference' => $this->media_storage_preference,
        ];
    }
}
