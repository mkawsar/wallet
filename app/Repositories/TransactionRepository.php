<?php

namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionRepository extends AbstractRepository
{
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    /**
     * Get transactions for a user (both sent and received).
     */
    public function getUserTransactions(int $userId, int $perPage = 50): LengthAwarePaginator
    {
        return $this->query()
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                    ->orWhere('receiver_id', $userId);
            })
            ->with([
                'sender' => function ($query) {
                    $query->select('id', 'name', 'email');
                },
                'receiver' => function ($query) {
                    $query->select('id', 'name', 'email');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new transaction.
     */
    public function createTransaction(int $senderId, int $receiverId, float $amount, float $commissionFee): Transaction
    {
        return $this->create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'amount' => $amount,
            'commission_fee' => $commissionFee,
        ]);
    }

    /**
     * Get transaction with relationships.
     */
    public function findWithRelations(int $id): ?Transaction
    {
        return $this->query()
            ->with([
                'sender' => function ($query) {
                    $query->select('id', 'name', 'email');
                },
                'receiver' => function ($query) {
                    $query->select('id', 'name', 'email');
                },
            ])
            ->find($id);
    }
}
