<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository extends AbstractRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Update user balance atomically using database-level increment/decrement.
     * This prevents race conditions in high-concurrency scenarios.
     *
     * @param  User  $user  The user model (must be locked with lockForUpdate)
     * @param  float  $amount  The amount to add (can be negative for deductions)
     * @return bool True on success
     */
    public function updateBalance(User $user, float $amount): bool
    {
        // Use atomic database increment/decrement to prevent race conditions
        // This ensures balance updates are thread-safe even with hundreds of concurrent transfers
        $affected = $this->model->where('id', $user->id)
            ->where('balance', '>=', $amount < 0 ? abs($amount) : 0) // Prevent negative balance
            ->increment('balance', $amount);

        if ($affected > 0) {
            // Refresh the model to get updated balance
            $user->refresh();

            return true;
        }

        return false;
    }

    /**
     * Search users by ID, name, or email.
     */
    public function search(string $query, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $currentUserId = request()->user()->id;

        return $this->query()
            ->where('id', '!=', $currentUserId) // Exclude current user
            ->where(function ($q) use ($query) {
                // If query is numeric, search by ID
                if (is_numeric($query)) {
                    $q->where('id', $query);
                } else {
                    // Otherwise search by name or email
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                }
            })
            ->select('id', 'name', 'email')
            ->limit($limit)
            ->get();
    }
}
