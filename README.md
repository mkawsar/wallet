# Mini Wallet Application

A high-performance digital wallet application built with Laravel and Vue.js, featuring real-time transaction updates via Pusher.

## Features

- **Money Transfers**: Send money between users with automatic commission calculation (1.5%)
- **Transaction History**: View all incoming and outgoing transactions
- **Real-time Updates**: Instant balance and transaction updates via Pusher
- **High Concurrency Support**: Database locking and atomic transactions prevent race conditions
- **Scalable Balance Management**: Balance stored in users table (not calculated on-the-fly)
- **Form Validation**: Real-time validation with user-friendly error messages
- **Currency Formatting**: Professional currency display

## Technology Stack

- **Backend**: Laravel 12
- **Frontend**: Vue.js 3 (Composition API)
- **Database**: MySQL/PostgreSQL
- **Real-time**: Pusher
- **Authentication**: Laravel Sanctum

## Requirements

- PHP 8.2+
- Composer
- Node.js & NPM
- MySQL/PostgreSQL
- Pusher account (for real-time features)

## Installation

1. **Clone the repository and install dependencies:**

```bash
composer install
npm install
```

2. **Configure environment:**

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
php artisan key:generate
```

Update the following in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wallet
DB_USERNAME=your_username
DB_PASSWORD=your_password

BROADCAST_DRIVER=pusher

PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_APP_CLUSTER=mt1
```

Also add to `.env`:

```env
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

3. **Run migrations:**

```bash
php artisan migrate
```

4. **Create users:**

You can use Laravel Tinker or create a seeder:

```bash
php artisan tinker
```

```php
User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password'),
    'balance' => 1000.00
]);
```

5. **Build frontend assets:**

```bash
npm run build
# or for development:
npm run dev
```

6. **Start the development server:**

```bash
php artisan serve
```

## API Endpoints

All API endpoints require authentication via Laravel Sanctum.

### GET /api/transactions
Returns transaction history and current balance for the authenticated user.

**Response:**
```json
{
    "balance": 1000.00,
    "transactions": {
        "data": [...]
    }
}
```

### POST /api/transactions
Executes a new money transfer.

**Request:**
```json
{
    "receiver_id": 2,
    "amount": 100.00
}
```

**Response:**
```json
{
    "message": "Transfer completed successfully",
    "transaction": {...},
    "new_balance": 898.50
}
```

### GET /api/user
Returns the authenticated user information.

## Architecture Highlights

### High Concurrency Handling

The application uses database row locking (`lockForUpdate()`) to prevent race conditions when multiple transfers occur simultaneously:

```php
$sender = User::lockForUpdate()->findOrFail($sender->id);
$receiver = User::lockForUpdate()->findOrFail($receiverId);
```

### Atomic Transactions

All balance updates and transaction creation are wrapped in database transactions to ensure data integrity:

```php
DB::beginTransaction();
// ... perform operations ...
DB::commit();
```

### Scalable Balance Management

User balances are stored directly in the `users` table, avoiding expensive calculations on millions of transaction records. The balance is updated atomically with each transaction.

### Real-time Updates

Transactions are broadcast to both sender and receiver via Pusher private channels:

```php
broadcast(new TransactionCompleted($transaction))->toOthers();
```

## Testing

To test the application:

1. Create at least 2 users with initial balances
2. Log in as one user
3. Navigate to `/wallet`
4. Send money to another user
5. Both users should see real-time updates

## Security Features

- CSRF protection
- Input validation
- SQL injection prevention (Eloquent ORM)
- Authentication required for all API endpoints
- Private channels for real-time updates

## Performance Considerations

- Database indexes on `sender_id`, `receiver_id`, and `created_at` in transactions table
- Pagination for transaction history (50 per page)
- Efficient queries with eager loading
- Row-level locking for concurrent operations

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
