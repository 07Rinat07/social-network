<?php

namespace App\Http\Resources\PostImage;

use Illuminate\Http\Resources\Json\JsonResource;

class PostImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'storage_disk' => $this->storage_disk,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'size' => (int) ($this->size ?? 0),
            'original_name' => $this->original_name,
            'url' => $this->url,
        ];
    }
}
