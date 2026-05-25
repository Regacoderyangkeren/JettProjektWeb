<?php

namespace Tests\Unit;

use App\Services\Firebase\FirebaseService;
use Tests\TestCase;

class FirebaseServiceTest extends TestCase
{
    public function test_firestore_uses_rest_transport_by_default(): void
    {
        config(['jettprojekt.firebase.firestore_transport' => 'rest']);

        $this->assertSame('rest', (new FirebaseService)->firestoreTransport());
    }

    public function test_invalid_transport_falls_back_to_rest(): void
    {
        config(['jettprojekt.firebase.firestore_transport' => 'unknown']);

        $this->assertSame('rest', (new FirebaseService)->firestoreTransport());
    }
}
