<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    description: 'Utilisateur connecté',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 7),
        new OA\Property(property: 'username', type: 'string', example: 'noctambule44'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'nuit@example.com'),
    ],
)]
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
        ];
    }
}
