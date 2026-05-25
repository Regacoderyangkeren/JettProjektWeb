<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Firebase\FirebaseService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function firebase(FirebaseService $firebase): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'firebase' => [
                'projectId' => $firebase->projectId(),
                'database' => $firebase->firestoreDatabase(),
                'credentialsConfigured' => $firebase->credentialsConfigured(),
                'transport' => extension_loaded('grpc') ? 'grpc' : 'rest',
            ],
            'collections' => config('jettprojekt.collections'),
        ]);
    }
}
