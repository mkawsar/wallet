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
     * Find user by ID with lock for update.
     */
    public function findWithLock(int $id): ?User
    {
        return $this->lockForUpdate($id);
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Update user balance.
     */
    public function updateBalance(User $user, float $amount): bool
    {
        $user->balance += $amount;

        return $user->save();
    }

    /**
     * Get user by ID.
     */
    public function getUser(int $userId): ?User
    {
        return $this->find($userId);
    }
}
