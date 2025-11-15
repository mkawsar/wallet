# Testing Documentation

## Overview

This project uses PHPUnit for testing with Mockery for mocking dependencies. The test suite includes unit tests for services and repositories, as well as feature tests for API controllers.

## Test Structure

```
tests/
├── Unit/
│   ├── Services/
│   │   └── TransactionServiceTest.php
│   └── Repositories/
│       └── TransactionRepositoryTest.php
└── Feature/
    └── Api/
        └── V1/
            └── TransactionControllerTest.php
```

## Running Tests

### Run all tests
```bash
php artisan test
```

### Run specific test suite
```bash
# Unit tests only
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature
```

### Run specific test class
```bash
php artisan test tests/Unit/Services/TransactionServiceTest.php
```

### Run with coverage
```bash
php artisan test --coverage
```

## Test Coverage

### Unit Tests

#### TransactionServiceTest
Tests the `TransactionService` class with mocked repositories:
- ✅ `getUserTransactions()` - Returns balance and transactions
- ✅ `getUserTransactions()` - Throws exception when user not found
- ✅ `executeTransfer()` - Successfully executes transfer
- ✅ `executeTransfer()` - Throws exception when receiver not found
- ✅ `executeTransfer()` - Throws exception when sending to self
- ✅ `executeTransfer()` - Throws exception when insufficient balance
- ✅ `executeTransfer()` - Rolls back on exception

#### TransactionRepositoryTest
Tests the `TransactionRepository` class with mocked models:
- ✅ `getUserTransactions()` - Returns paginated results
- ✅ `createTransaction()` - Creates new transaction
- ✅ `findWithRelations()` - Returns transaction with relationships
- ✅ `findWithRelations()` - Returns null when not found

### Feature Tests

#### TransactionControllerTest
Tests the API endpoints with mocked services:
- ✅ `GET /api/v1/transactions` - Returns user transactions and balance
- ✅ `POST /api/v1/transactions` - Creates transaction successfully
- ✅ `POST /api/v1/transactions` - Returns validation error for invalid receiver
- ✅ `POST /api/v1/transactions` - Returns validation error for invalid amount
- ✅ `POST /api/v1/transactions` - Returns validation error when sending to self
- ✅ `POST /api/v1/transactions` - Handles service exception
- ✅ Authentication required for all endpoints

## Mockery Usage

All tests use Mockery for mocking dependencies:

```php
use Mockery;

// Mock a repository
$repositoryMock = Mockery::mock(Repository::class);
$repositoryMock->shouldReceive('method')
    ->once()
    ->with($arg)
    ->andReturn($result);

// Mock a model with partial mocking
$modelMock = Mockery::mock(Model::class)->makePartial();
$modelMock->shouldReceive('method')->once();
```

## API Versioning

The API uses versioning with the following structure:

- **V1**: `/api/v1/*` - Current stable version
- **Default**: `/api/*` - Backward compatibility (routes to v1)

### Versioned Controllers

Controllers are organized by version:
```
app/Http/Controllers/Api/V1/
├── TransactionController.php
└── UserController.php
```

### Versioned Routes

Routes are defined in `routes/api.php`:
```php
// API Version 1
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
});
```

## Best Practices

1. **Isolation**: Each test is independent and doesn't rely on other tests
2. **Mocking**: All external dependencies are mocked using Mockery
3. **Assertions**: Tests use specific assertions to verify behavior
4. **Cleanup**: Tests properly clean up mocks in `tearDown()` method
5. **Naming**: Test methods use descriptive names that explain what they test

## Adding New Tests

When adding new functionality:

1. Create unit tests for services/repositories first
2. Create feature tests for API endpoints
3. Ensure all dependencies are properly mocked
4. Test both success and failure scenarios
5. Test edge cases and validation

## Example Test

```php
public function test_execute_transfer_successfully(): void
{
    Event::fake();
    DB::shouldReceive('beginTransaction')->once();
    DB::shouldReceive('commit')->once();

    // Setup mocks
    $this->userRepositoryMock
        ->shouldReceive('find')
        ->once()
        ->andReturn($receiver);

    // Execute
    $result = $this->service->executeTransfer($senderId, $receiverId, $amount);

    // Assert
    $this->assertEquals($expected, $result);
    Event::assertDispatched(TransactionCompleted::class);
}
```

