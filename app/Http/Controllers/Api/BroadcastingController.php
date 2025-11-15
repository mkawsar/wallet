<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastingController extends Controller
{
    /**
     * Authenticate the request for channel access.
     */
    public function authenticate(Request $request): JsonResponse
    {
        // Ensure we always return JSON
        $request->headers->set('Accept', 'application/json');

        // Try to authenticate user via Sanctum (supports both Bearer token and session)
        $user = $request->user();

        // If no user, try to authenticate manually with Bearer token
        if (! $user && $request->bearerToken()) {
            try {
                // Attempt to authenticate using Sanctum token
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken())?->tokenable;
            } catch (\Exception $e) {
                \Log::warning('Failed to authenticate with Bearer token', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Get channel name and socket ID from request
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        if (! $channelName || ! $socketId) {
            return response()->json([
                'message' => 'Missing channel_name or socket_id.',
            ], 400);
        }

        // Use manual authorization with channels.php logic
        // This ensures proper authentication and JSON responses
        try {
            return $this->manualAuth($request, $channelName, $socketId, $user);
        } catch (\Exception $e) {
            \Log::error('Channel authorization failed', [
                'channel' => $channelName,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Channel authorization failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manual channel authorization.
     * Implements the same logic as routes/channels.php for user channels.
     */
    private function manualAuth(Request $request, string $channelName, string $socketId, $user): JsonResponse
    {
        // Parse private channel name (e.g., "private-user.123")
        if (strpos($channelName, 'private-') !== 0) {
            return response()->json([
                'message' => 'Invalid channel type.',
            ], 403);
        }

        $actualChannel = str_replace('private-', '', $channelName);

        // Check if it matches our user channel pattern
        if (! preg_match('/^user\.(\d+)$/', $actualChannel, $matches)) {
            return response()->json([
                'message' => 'Invalid channel format.',
            ], 403);
        }

        $userId = (int) $matches[1];
        $currentUserId = (int) $user->id;

        // Authorize: user can only subscribe to their own channel
        if ($userId !== $currentUserId) {
            \Log::warning('Unauthorized channel access attempt', [
                'user_id' => $currentUserId,
                'requested_channel_user_id' => $userId,
            ]);

            return response()->json([
                'message' => 'Unauthorized to access this channel.',
            ], 403);
        }

        // Generate auth signature
        try {
            $auth = $this->generateAuthSignature($channelName, $socketId);

            return response()->json($auth);
        } catch (\Exception $e) {
            \Log::error('Failed to generate auth signature', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to generate authentication signature.',
            ], 500);
        }
    }

    /**
     * Generate authentication signature for Pusher channels.
     */
    private function generateAuthSignature(string $channelName, string $socketId): array
    {
        $pusherSecret = config('broadcasting.connections.pusher.secret');

        if (! $pusherSecret) {
            throw new \Exception('Pusher secret not configured. Please set PUSHER_APP_SECRET in your .env file.');
        }

        $pusherKey = config('broadcasting.connections.pusher.key');

        if (! $pusherKey) {
            throw new \Exception('Pusher key not configured. Please set PUSHER_APP_KEY in your .env file.');
        }

        $stringToSign = $socketId.':'.$channelName;
        $signature = hash_hmac('sha256', $stringToSign, $pusherSecret, false);

        return [
            'auth' => $pusherKey.':'.$signature,
        ];
    }
}
