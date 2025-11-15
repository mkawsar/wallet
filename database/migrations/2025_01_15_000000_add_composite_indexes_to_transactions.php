<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add composite indexes for optimal query performance with millions of rows.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Composite index for queries filtering by sender_id and ordering by created_at
            // This significantly improves performance for user transaction history queries
            $table->index(['sender_id', 'created_at'], 'idx_sender_created_at');

            // Composite index for queries filtering by receiver_id and ordering by created_at
            $table->index(['receiver_id', 'created_at'], 'idx_receiver_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_sender_created_at');
            $table->dropIndex('idx_receiver_created_at');
        });
    }
};
