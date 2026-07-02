<?php

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Character
 */
class CharacterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'personality' => $this->personality,
            'status' => $this->status->value,
            'isProcessing' => $this->status->isProcessing(),
            'failureReason' => $this->failure_reason,
            'imageUrl' => $this->imageUrl(),
            'drawingUrl' => $this->drawingUrl(),
            'runwayAvatarId' => $this->runway_avatar_id,
        ];
    }
}
