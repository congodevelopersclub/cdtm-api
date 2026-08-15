<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Profile
 */
class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'name'           => $this->name,
            'email'          => $this->email,
            'headline'       => $this->headline,
            'bio'            => $this->bio,
            'avatar_url'     => $this->avatar_url,
            'location'       => $this->location,
            'status'         => $this->status,
            'account_status' => $this->account_status,
            'skills'         => SkillListResource::collection($this->whenLoaded('skills')),
            'projects'       => $this->whenLoaded('projects'),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
