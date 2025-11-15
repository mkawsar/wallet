<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Ensure relationships are loaded to avoid N+1 queries
        $this->resource->loadMissing([
            'sender' => function ($query) {
                $query->select('id', 'name', 'email');
            },
            'receiver' => function ($query) {
                $query->select('id', 'name', 'email');
            },
        ]);

        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->receiver_id,
            'amount' => (float) $this->amount,
            'commission_fee' => (float) $this->commission_fee,
            'created_at' => $this->created_at->toISOString(),
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'email' => $this->sender->email,
            ],
            'receiver' => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
                'email' => $this->receiver->email,
            ],
        ];
    }
}
