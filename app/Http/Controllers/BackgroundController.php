<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

// Placeholder for Task 2 route registration only — Task 3 replaces this controller entirely.
class BackgroundController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
