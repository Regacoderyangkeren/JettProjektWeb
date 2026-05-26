<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Firebase\FirebaseService;
use Illuminate\Http\JsonResponse;
use Throwable;

class HealthController extends Controller
{
    public function firebase(FirebaseService $firebase): JsonResponse
    {
        $firestoreRead = [
            'ok' => false,
            'errorType' => null,
        ];

        try {
            foreach ($firebase->firestore()->collection($firebase->collectionName('users'))->limit(1)->documents() as $document) {
                break;
            }

            $firestoreRead['ok'] = true;
        } catch (Throwable $exception) {
            report($exception);
            $firestoreRead['errorType'] = class_basename($exception);
        }

        return response()->json([
            'ok' => true,
            'firebase' => [
                'projectId' => $firebase->projectId(),
                'database' => $firebase->firestoreDatabase(),
                'credentialsConfigured' => $firebase->credentialsConfigured(),
                'transport' => $firebase->firestoreTransport(),
                'firestoreRead' => $firestoreRead,
            ],
            'collections' => config('jettprojekt.collections'),
        ]);
    }
}
