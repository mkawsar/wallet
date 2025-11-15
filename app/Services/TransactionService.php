<?php

namespace App\Services;

use App\Events\TransactionCompleted;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    private const COMMISSION_RATE = 0.015; // 1.5%

    public function __construct(
        private TransactionRepository $transactionRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * Get user transactions and balance.
     */
    public function getUserTransactions(int $userId, int $perPage = 10): array
    {
        $user = $this->userRepository->find($userId);
        if (! $user) {
            throw new \RuntimeException('User not found.');
        }

        $transactions = $this->transactionRepository->getUserTransactions($userId, $perPage);

        return [
            'balance' => (float) $user->balance,
            'transactions' => $transactions,
        ];
    }

    /**
     * Execute a money transfer.
     *
     * @throws ValidationException
     */
    public function executeTransfer(int $senderId, int $receiverId, float $amount): array
    {
        // Validate receiver exists
        $receiver = $this->userRepository->find($receiverId);
        if (! $receiver) {
            throw ValidationException::withMessages([
                'receiver_id' => ['The selected receiver does not exist.'],
            ]);
        }

        // Validate sender cannot send to themselves
        if ($senderId === $receiverId) {
            throw ValidationException::withMessages([
                'receiver_id' => ['You cannot send money to yourself.'],
            ]);
        }

        // Calculate commission and total debit
        $commissionFee = round($amount * self::COMMISSION_RATE, 2);
        $totalDebit = $amount + $commissionFee;

        // Use database transaction with proper isolation level for high concurrency
        try {
            // Set transaction isolation level to READ COMMITTED for better concurrency
            // This allows multiple transactions to proceed while maintaining data integrity
            // Note: Some databases may not support this, so we catch any exceptions
            try {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            } catch (\Exception $e) {
                // Ignore if isolation level setting is not supported
                // The default isolation level will be used
            }
            DB::beginTransaction();

            // Lock users in consistent order (by ID) to prevent deadlocks
            // This is critical for handling hundreds of concurrent transfers per second
            // Always lock the user with the smaller ID first to prevent circular wait conditions
            $firstUserId = min($senderId, $receiverId);
            $secondUserId = max($senderId, $receiverId);

            $firstUser = $this->userRepository->lockForUpdate($firstUserId);
            $secondUser = $this->userRepository->lockForUpdate($secondUserId);

            // Assign sender and receiver based on which was locked first
            if ($firstUserId === $senderId) {
                $sender = $firstUser;
                $receiver = $secondUser;
            } else {
                $sender = $secondUser;
                $receiver = $firstUser;
            }

            // Validate users exist and are correct
            if (! $sender || ! ($sender instanceof \App\Models\User) || $sender->id !== $senderId) {
                throw ValidationException::withMessages([
                    'sender_id' => ['Sender not found.'],
                ]);
            }

            if (! $receiver || ! ($receiver instanceof \App\Models\User) || $receiver->id !== $receiverId) {
                throw ValidationException::withMessages([
                    'receiver_id' => ['Receiver not found.'],
                ]);
            }

            // Validate sender has sufficient balance (double-check after locking)
            if ($sender->balance < $totalDebit) {
                throw ValidationException::withMessages([
                    'amount' => [
                        'Insufficient balance. Required: '.number_format((float) $totalDebit, 2).
                        ', Available: '.number_format((float) $sender->balance, 2),
                    ],
                ]);
            }

            // Update balances atomically using database-level operations
            // This prevents race conditions even with hundreds of concurrent transfers per second
            $this->userRepository->updateBalance($sender, -$totalDebit);
            $this->userRepository->updateBalance($receiver, $amount);

            // Create transaction record
            $transaction = $this->transactionRepository->createTransaction(
                $senderId,
                $receiverId,
                $amount,
                $commissionFee
            );

            // Refresh both sender and receiver to get updated balances
            $sender->refresh();
            $receiver->refresh();

            DB::commit();

            // Load transaction with relationships
            $transaction = $this->transactionRepository->findWithRelations($transaction->id);

            // Broadcast event to both sender and receiver (synchronously for immediate delivery)
            try {
                $event = new TransactionCompleted($transaction);
                broadcast($event);
            } catch (\Exception $e) {
                // Log but don't fail the transaction if broadcasting fails
                $errorMessage = $e->getMessage();
                $isTimestampError = str_contains($errorMessage, 'Timestamp expired') ||
                                    str_contains($errorMessage, 'timestamp');

                \Log::error('Broadcasting failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $errorMessage,
                    'is_timestamp_error' => $isTimestampError,
                ]);

                // If it's a timestamp error, provide helpful message
                if ($isTimestampError) {
                    \Log::warning('Server time may be out of sync with Pusher. Please sync your system clock using NTP.');
                }
            }

            return [
                'transaction' => $transaction,
                'new_balance' => (float) $sender->balance,
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \RuntimeException('Transfer failed: '.$e->getMessage(), 0, $e);
        }
    }
}
