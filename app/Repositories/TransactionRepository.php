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
     * Optimized for millions of rows using efficient index usage.
     * Uses a composite query that leverages indexes on sender_id and receiver_id.
     */
    public function getUserTransactions(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        // For optimal performance with millions of rows, we use a query that can leverage
        // both sender_id and receiver_id indexes. The database optimizer will choose
        // the best execution plan based on available indexes.
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
            ->orderBy('id', 'desc') // Secondary sort for consistent pagination
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
