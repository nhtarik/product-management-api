<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'parent_id'         => $this->parent_id,
            'parent'            => $this->parent ? $this->parent->name : null,
            'has_children'      => $this->children()->exists(),
            'total_children'    => $this->children()->count(),
            'children'          => CategoryResource::collection($this->whenLoaded('children')),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
