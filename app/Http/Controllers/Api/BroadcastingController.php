<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BroadcastingController extends Controller
{
    /**
     * Authenticate the request for channel access.
     */
    public function authenticate(Request $request): JsonResponse
    {
        // Ensure we always return JSON
        $request->headers->set('Accept', 'application/json');
        
        // Log authentication attempt for debugging
        Log::debug('Broadcasting auth request', [
            'has_bearer_token' => $request->bearerToken() !== null,
            'has_session' => $request->hasSession(),
            'auth_guard' => 'sanctum',
            'headers' => $request->headers->all(),
        ]);
        
        // Try to authenticate user via Sanctum (supports both Bearer token and session)
        $user = $request->user();
        
        // If no user, try to authenticate manually with Bearer token
        if (!$user && $request->bearerToken()) {
            try {
                // Attempt to authenticate using Sanctum token
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken())?->tokenable;
            } catch (\Exception $e) {
                Log::warning('Failed to authenticate with Bearer token', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        if (!$user) {
            Log::warning('Broadcasting auth failed: No authenticated user', [
                'has_bearer_token' => $request->bearerToken() !== null,
                'has_session' => $request->hasSession(),
            ]);
            
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Get channel name and socket ID from request
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        
        if (!$channelName || !$socketId) {
            Log::warning('Broadcasting auth failed: Missing parameters', [
                'has_channel_name' => !empty($channelName),
                'has_socket_id' => !empty($socketId),
            ]);
            
            return response()->json([
                'message' => 'Missing channel_name or socket_id.'
            ], 400);
        }
        
        // Use manual authorization with channels.php logic
        // This ensures proper authentication and JSON responses
        try {
            Log::debug('Processing channel authorization', [
                'user_id' => $user->id,
                'channel' => $channelName,
            ]);
            
            return $this->manualAuth($request, $channelName, $socketId, $user);
        } catch (\Exception $e) {
            Log::error('Channel authorization failed: ' . $e->getMessage(), [
                'channel' => $channelName,
                'socket_id' => $socketId,
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Channel authorization failed: ' . $e->getMessage()
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
            Log::warning('Invalid channel type', ['channel' => $channelName]);
            return response()->json([
                'message' => 'Invalid channel type.'
            ], 403);
        }
        
        $actualChannel = str_replace('private-', '', $channelName);
        
        // Check if it matches our user channel pattern
        if (!preg_match('/^user\.(\d+)$/', $actualChannel, $matches)) {
            Log::warning('Invalid channel format', ['channel' => $actualChannel]);
            return response()->json([
                'message' => 'Invalid channel format.'
            ], 403);
        }
        
        $userId = (int) $matches[1];
        $currentUserId = (int) $user->id;
        
        // Authorize: user can only subscribe to their own channel
        if ($userId !== $currentUserId) {
            Log::warning('Unauthorized channel access attempt', [
                'user_id' => $currentUserId,
                'requested_channel_user_id' => $userId,
            ]);
            return response()->json([
                'message' => 'Unauthorized to access this channel.'
            ], 403);
        }
        
        // Generate auth signature
        try {
            $auth = $this->generateAuthSignature($channelName, $socketId);
            Log::debug('Channel authorization successful', [
                'user_id' => $currentUserId,
                'channel' => $channelName,
            ]);
            return response()->json($auth);
        } catch (\Exception $e) {
            Log::error('Failed to generate auth signature: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate authentication signature.'
            ], 500);
        }
    }
    
    /**
     * Generate authentication signature for Pusher channels.
     */
    private function generateAuthSignature(string $channelName, string $socketId): array
    {
        $pusherSecret = config('broadcasting.connections.pusher.secret');
        
        if (!$pusherSecret) {
            throw new \Exception('Pusher secret not configured. Please set PUSHER_APP_SECRET in your .env file.');
        }
        
        $pusherKey = config('broadcasting.connections.pusher.key');
        
        if (!$pusherKey) {
            throw new \Exception('Pusher key not configured. Please set PUSHER_APP_KEY in your .env file.');
        }
        
        $stringToSign = $socketId . ':' . $channelName;
        $signature = hash_hmac('sha256', $stringToSign, $pusherSecret, false);
        
        return [
            'auth' => $pusherKey . ':' . $signature
        ];
    }
}
