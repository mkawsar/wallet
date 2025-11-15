<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function ($user, $userId) {
    // Support both web auth and Sanctum
    if (!$user) {
        return false;
    }
    return (int) $user->id === (int) $userId;
});
