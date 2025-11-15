<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    /**
     * Get the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->userRepository->getUser($request->user()->id);

        return response()->json(new UserResource($user));
    }
}
