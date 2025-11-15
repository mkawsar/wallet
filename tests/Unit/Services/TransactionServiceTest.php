<?php

namespace Tests\Unit\Services;

use App\Events\TransactionCompleted;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Services\TransactionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    private TransactionService $service;

    private $transactionRepositoryMock;

    private $userRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionRepositoryMock = Mockery::mock(TransactionRepository::class);
        $this->userRepositoryMock = Mockery::mock(UserRepository::class);

        $this->service = new TransactionService(
            $this->transactionRepositoryMock,
            $this->userRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_user_transactions_returns_balance_and_transactions(): void
    {
        $userId = 1;
        $user = new User;
        $user->id = $userId;
        $user->balance = 1000.00;

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->userRepositoryMock
            ->shouldReceive('getUser')
            ->once()
            ->with($userId)
            ->andReturn($user);

        $this->transactionRepositoryMock
            ->shouldReceive('getUserTransactions')
            ->once()
            ->with($userId)
            ->andReturn($paginator);

        $result = $this->service->getUserTransactions($userId);

        $this->assertEquals(1000.00, $result['balance']);
        $this->assertEquals($paginator, $result['transactions']);
    }

    public function test_get_user_transactions_throws_exception_when_user_not_found(): void
    {
        $userId = 999;

        $this->userRepositoryMock
            ->shouldReceive('getUser')
            ->once()
            ->with($userId)
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not found.');

        $this->service->getUserTransactions($userId);
    }

    public function test_execute_transfer_successfully(): void
    {
        Event::fake();
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();
        DB::shouldReceive('rollBack')->never();

        $senderId = 1;
        $receiverId = 2;
        $amount = 100.00;
        $commissionFee = 1.50;
        $totalDebit = 101.50;

        $sender = Mockery::mock(User::class)->makePartial();
        $sender->id = $senderId;
        $sender->balance = 500.00;

        $receiver = Mockery::mock(User::class)->makePartial();
        $receiver->id = $receiverId;
        $receiver->balance = 200.00;

        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->sender_id = $senderId;
        $transaction->receiver_id = $receiverId;
        $transaction->amount = $amount;
        $transaction->commission_fee = $commissionFee;

        $transactionWithRelations = new Transaction;
        $transactionWithRelations->id = 1;
        $transactionWithRelations->sender_id = $senderId;
        $transactionWithRelations->receiver_id = $receiverId;
        $transactionWithRelations->amount = $amount;
        $transactionWithRelations->commission_fee = $commissionFee;

        // Set relationships to avoid database queries in event
        $transactionWithRelations->setRelation('sender', $sender);
        $transactionWithRelations->setRelation('receiver', $receiver);

        // Mock receiver exists check
        $this->userRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($receiverId)
            ->andReturn($receiver);

        // Mock locked users
        $this->userRepositoryMock
            ->shouldReceive('findWithLock')
            ->once()
            ->with($senderId)
            ->andReturn($sender);

        $this->userRepositoryMock
            ->shouldReceive('findWithLock')
            ->once()
            ->with($receiverId)
            ->andReturn($receiver);

        // Mock balance updates
        $this->userRepositoryMock
            ->shouldReceive('updateBalance')
            ->once()
            ->with($sender, -$totalDebit)
            ->andReturn(true);

        $this->userRepositoryMock
            ->shouldReceive('updateBalance')
            ->once()
            ->with($receiver, $amount)
            ->andReturn(true);

        // Mock transaction creation
        $this->transactionRepositoryMock
            ->shouldReceive('createTransaction')
            ->once()
            ->with($senderId, $receiverId, $amount, $commissionFee)
            ->andReturn($transaction);

        // Mock refresh to update balance
        $sender->shouldReceive('refresh')
            ->once()
            ->andReturnSelf();

        // Set updated balance after refresh
        $sender->balance = 398.50;

        // Mock find with relations
        $this->transactionRepositoryMock
            ->shouldReceive('findWithRelations')
            ->once()
            ->with($transaction->id)
            ->andReturn($transactionWithRelations);

        $result = $this->service->executeTransfer($senderId, $receiverId, $amount);

        $this->assertEquals($transactionWithRelations, $result['transaction']);
        $this->assertEquals(398.50, $result['new_balance']);

        Event::assertDispatched(TransactionCompleted::class);
    }

    public function test_execute_transfer_throws_exception_when_receiver_not_found(): void
    {
        DB::shouldReceive('beginTransaction')->never();
        DB::shouldReceive('rollBack')->never();

        $senderId = 1;
        $receiverId = 999;
        $amount = 100.00;

        $this->userRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($receiverId)
            ->andReturn(null);

        $this->expectException(ValidationException::class);

        try {
            $this->service->executeTransfer($senderId, $receiverId, $amount);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receiver_id', $e->errors());
            throw $e;
        }
    }

    public function test_execute_transfer_throws_exception_when_sender_sends_to_themselves(): void
    {
        DB::shouldReceive('beginTransaction')->never();
        DB::shouldReceive('rollBack')->never();

        $userId = 1;
        $amount = 100.00;

        $user = new User;
        $user->id = $userId;

        $this->userRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($userId)
            ->andReturn($user);

        $this->expectException(ValidationException::class);

        try {
            $this->service->executeTransfer($userId, $userId, $amount);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receiver_id', $e->errors());
            throw $e;
        }
    }

    public function test_execute_transfer_throws_exception_when_insufficient_balance(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();

        $senderId = 1;
        $receiverId = 2;
        $amount = 100.00;
        $commissionFee = 1.50;
        $totalDebit = 101.50;

        $sender = new User;
        $sender->id = $senderId;
        $sender->balance = 50.00; // Insufficient balance

        $receiver = new User;
        $receiver->id = $receiverId;

        $this->userRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($receiverId)
            ->andReturn($receiver);

        $this->userRepositoryMock
            ->shouldReceive('findWithLock')
            ->once()
            ->with($senderId)
            ->andReturn($sender);

        $this->userRepositoryMock
            ->shouldReceive('findWithLock')
            ->once()
            ->with($receiverId)
            ->andReturn($receiver);

        $this->expectException(ValidationException::class);

        try {
            $this->service->executeTransfer($senderId, $receiverId, $amount);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
            throw $e;
        }
    }

    public function test_execute_transfer_rolls_back_on_exception(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();

        $senderId = 1;
        $receiverId = 2;
        $amount = 100.00;

        $receiver = new User;
        $receiver->id = $receiverId;

        $this->userRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($receiverId)
            ->andReturn($receiver);

        $this->userRepositoryMock
            ->shouldReceive('findWithLock')
            ->once()
            ->with($senderId)
            ->andThrow(new \Exception('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transfer failed: Database error');

        $this->service->executeTransfer($senderId, $receiverId, $amount);
    }
}
