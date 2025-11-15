# Mini Wallet Application

A high-performance digital wallet application built with Laravel and Vue.js, featuring real-time transaction updates via Pusher.

## Features

- **Money Transfers**: Send money between users with automatic commission calculation (1.5%)
- **Transaction History**: View all incoming and outgoing transactions with pagination support
- **Real-time Updates**: Instant balance and transaction updates via Pusher WebSockets
- **High Concurrency Support**: Database locking and atomic transactions prevent race conditions
- **Scalable Balance Management**: Balance stored in users table (not calculated on-the-fly)
- **Form Validation**: Real-time validation with user-friendly error messages
- **Currency Formatting**: Professional currency display
- **Pagination**: Efficient pagination for large transaction histories

## Technology Stack

- **Backend**: Laravel 12
- **Frontend**: Vue.js 3 (Composition API)
- **Database**: MySQL/PostgreSQL
- **Real-time**: Pusher
- **Authentication**: Laravel Sanctum

## Requirements

Before you begin, ensure you have the following installed:

- **PHP** 8.2 or higher
- **Composer** (PHP dependency manager)
- **Node.js** 18+ and **NPM** (or Yarn)
- **MySQL/PostgreSQL** (or SQLite for development)
- **Pusher account** (free tier available at [pusher.com](https://pusher.com))

## Installation & Setup

### Step 1: Clone the Repository

```bash
git clone https://github.com/mkawsar/wallet
cd wallet
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

This will install all Laravel and PHP dependencies defined in `composer.json`.

### Step 3: Install Node.js Dependencies

```bash
npm install
```

This will install all frontend dependencies including Vue.js, Laravel Echo, and Pusher JS.

### Step 4: Environment Configuration

1. **Copy the environment file:**

```bash
cp .env.example .env
```

2. **Generate application key:**

```bash
php artisan key:generate
```

3. **Configure database settings in `.env`:**

**For MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wallet
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

**For PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=wallet
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password
```

**Important Notes:**
- Replace `your_mysql_password` or `your_postgres_password` with your actual database password
- Make sure the database `wallet` exists before running migrations (see Step 5)
- For remote databases, update `DB_HOST` with the correct hostname or IP address
- Default ports: MySQL uses `3306`, PostgreSQL uses `5432`
- If using a different database user, update `DB_USERNAME` accordingly

4. **Configure Pusher settings in `.env`:**

First, create a free account at [pusher.com](https://pusher.com) and create a new app. Then add your credentials:

```env
BROADCAST_DRIVER=pusher

PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_APP_CLUSTER=mt1
```

5. **Add Vite environment variables for frontend:**

```env
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### Step 5: Database Setup

1. **Create the database** (if using MySQL/PostgreSQL):

```bash
# MySQL
mysql -u root -p
CREATE DATABASE wallet;
EXIT;

# Or use your database management tool
```

2. **Run migrations:**

```bash
php artisan migrate
```

This will create all necessary database tables:
- `users` - User accounts with balance
- `transactions` - Transaction history
- `personal_access_tokens` - Sanctum authentication tokens
- `cache`, `jobs` - Laravel system tables

### Step 6: Create Test Users
Then create users:

```bash
php artisan db:seed
```

### Step 7: Build Frontend Assets

**For Development** (with hot reload):
```bash
npm run dev
```

**For Production**:
```bash
npm run build
```

Keep the dev server running in a separate terminal if using `npm run dev`.

## Running the Application

### Start the Laravel Development Server

Open a new terminal window and run:

```bash
php artisan serve
```

The application will be available at: `http://localhost:8000`

### Access the Application

1. **Open your browser** and navigate to: `http://localhost:8000`

2. **Login** with one of the test users you created

3. **Navigate to the wallet page**: `http://localhost:8000/wallet`

4. **Test the application:**
   - Send money to another user
   - View transaction history
   - Test real-time updates by opening the wallet page in two different browsers (logged in as different users)

## Development Workflow

### Running Both Servers

You need to run both the Laravel server and the Vite dev server simultaneously:

**Terminal 1 - Laravel Server:**
```bash
php artisan serve
```

**Terminal 2 - Vite Dev Server (for development):**
```bash
npm run dev
```

**Note:** For production, build assets once with `npm run build` and only run `php artisan serve`.

### Testing Real-time Features

To test real-time transaction updates:

1. Open the wallet page in **Browser 1** - Login as User 1
2. Open the wallet page in **Browser 2** (or incognito) - Login as User 2
3. From Browser 1, send money to User 2
4. Browser 2 should automatically update without refreshing

## API Endpoints

All API endpoints require authentication via Laravel Sanctum Bearer token.

### Authentication

First, you need to obtain an authentication token. The token is automatically created when you visit `/wallet` page and is stored in `localStorage` as `auth_token`.

### GET /api/transactions

Returns paginated transaction history and current balance for the authenticated user.

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 10, max: 100)

**Example Request:**
```bash
GET /api/transactions?page=1&per_page=10
Authorization: Bearer {token}
```

**Response:**
```json
{
    "balance": 1000.00,
    "transactions": {
        "data": [
            {
                "id": 1,
                "sender_id": 1,
                "receiver_id": 2,
                "amount": 100.00,
                "commission_fee": 1.50,
                "created_at": "2025-01-15T10:30:00.000000Z",
                "sender": {
                    "id": 1,
                    "name": "John Doe",
                    "email": "john@example.com"
                },
                "receiver": {
                    "id": 2,
                    "name": "Jane Smith",
                    "email": "jane@example.com"
                }
            }
        ],
        "meta": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 10,
            "total": 45,
            "from": 1,
            "to": 10
        },
        "links": {
            "first": "http://localhost:8000/api/transactions?page=1",
            "last": "http://localhost:8000/api/transactions?page=5",
            "prev": null,
            "next": "http://localhost:8000/api/transactions?page=2"
        }
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
    "transaction": {
        "id": 1,
        "sender_id": 1,
        "receiver_id": 2,
        "amount": 100.00,
        "commission_fee": 1.50,
        "created_at": "2025-01-15T10:30:00.000000Z"
    },
    "new_balance": 898.50
}
```

**Validation Rules:**
- `receiver_id`: Required, must exist in users table, cannot be the same as sender
- `amount`: Required, numeric, minimum 0.01, maximum 999999999.99

### GET /api/user

Returns the authenticated user information.

**Response:**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
}
```

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

### Manual Testing Steps

1. **Create at least 2 users** with initial balances (see Step 6 in Installation)

2. **Login as User 1:**
   - Navigate to `http://localhost:8000`
   - Login with `john@example.com` / `password`
   - Go to `/wallet` page

3. **Login as User 2** (in another browser/incognito):
   - Navigate to `http://localhost:8000`
   - Login with `jane@example.com` / `password`
   - Go to `/wallet` page

4. **Send money from User 1 to User 2:**
   - In User 1's browser, search for User 2
   - Enter an amount (e.g., 50.00)
   - Click "Send Money"

5. **Verify real-time updates:**
   - User 2's browser should automatically update:
     - Balance increases by the sent amount
     - New transaction appears in history
     - No page refresh needed

6. **Test pagination:**
   - Create multiple transactions (more than 10)
   - Verify pagination controls appear
   - Test Previous/Next buttons
   - Test page number buttons
   - Test "Go to page" input (if more than 10 pages)

### Running Automated Tests

```bash
# Run PHPUnit tests
php artisan test

# Run specific test file
php artisan test --filter TransactionServiceTest
```

## Security Features

- CSRF protection
- Input validation
- SQL injection prevention (Eloquent ORM)
- Authentication required for all API endpoints
- Private channels for real-time updates

## Troubleshooting

### Common Issues

**1. Pusher connection not working:**
- Verify Pusher credentials in `.env` are correct
- Check that `VITE_PUSHER_APP_KEY` and `VITE_PUSHER_APP_CLUSTER` are set
- Ensure you've rebuilt assets: `npm run build` or `npm run dev`
- Check browser console for connection errors
- Verify Pusher app is active in Pusher dashboard

**2. Real-time updates not received:**
- Ensure receiver is on `/wallet` page before transaction is sent
- Check browser console for subscription status
- Verify authentication token is valid
- Check Laravel logs: `storage/logs/laravel.log`

**3. Database connection errors:**
- Verify database credentials in `.env`
- Ensure database exists
- Check database server is running
- For SQLite, ensure `database/database.sqlite` file exists

**4. Frontend assets not loading:**
- Run `npm run build` or `npm run dev`
- Clear browser cache
- Check `public/build` directory exists

**5. Authentication errors:**
- Clear browser localStorage
- Logout and login again
- Check Sanctum token is being generated

### Debugging Real-time Issues

1. **Check browser console** for:
   - Pusher connection status
   - Channel subscription status
   - Event reception logs

2. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Verify Pusher dashboard:**
   - Go to your Pusher app dashboard
   - Check "Debug Console" for events
   - Verify events are being sent

## Performance Considerations

- **Database indexes** on `sender_id`, `receiver_id`, and `created_at` in transactions table
- **Pagination** for transaction history (default: 10 per page, configurable)
- **Efficient queries** with eager loading to prevent N+1 problems
- **Row-level locking** (`lockForUpdate()`) for concurrent operations
- **Atomic transactions** ensure data integrity
- **Balance caching** - balances stored in users table, not calculated on-the-fly

## Project Structure

```
wallet/
├── app/
│   ├── Events/
│   │   └── TransactionCompleted.php    # Broadcasting event
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── BroadcastingController.php  # Pusher auth
│   │   │   │   └── V1/
│   │   │   │       └── TransactionController.php
│   │   │   └── Auth/
│   │   └── Resources/
│   │       └── TransactionResource.php
│   ├── Models/
│   │   ├── Transaction.php
│   │   └── User.php
│   ├── Repositories/
│   │   ├── TransactionRepository.php
│   │   └── UserRepository.php
│   └── Services/
│       └── TransactionService.php
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── js/
│   │   └── components/
│   │       └── WalletApp.vue           # Main Vue component
│   └── views/
│       └── wallet.blade.php
├── routes/
│   ├── api.php                         # API routes
│   ├── channels.php                    # Broadcasting channels
│   └── web.php                         # Web routes
└── config/
    └── broadcasting.php                 # Pusher configuration
```

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Documentation](https://vuejs.org/)
- [Pusher Documentation](https://pusher.com/docs)
- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
