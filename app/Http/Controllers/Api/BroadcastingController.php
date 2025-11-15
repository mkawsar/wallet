<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastingController extends Controller
{
    /**
     * Authenticate the request for channel access.
     */
    public function authenticate(Request $request)
    {
        // For Sanctum API authentication
        if ($request->user()) {
            return Broadcast::auth($request);
        }

        // Fallback to session authentication
        return Broadcast::auth($request);
    }
}
