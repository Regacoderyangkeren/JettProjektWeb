<?php

namespace Tests\Feature;

use App\Http\Controllers\Concerns\ReadsFirebaseData;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class FirebaseReadRetryTest extends TestCase
{
    public function test_a_transient_read_failure_is_retried(): void
    {
        Log::spy();
        $attempts = 0;
        $reader = $this->reader();

        $result = $reader->read(function () use (&$attempts): array {
            $attempts++;
            if ($attempts === 1) {
                throw new RuntimeException('Temporary Firestore failure.');
            }

            return ['project'];
        }, []);

        $this->assertSame(['project'], $result);
        $this->assertSame(2, $attempts);
        $this->assertNull(session('firebase_read_warning'));
    }

    public function test_a_persistent_read_failure_displays_a_warning(): void
    {
        Log::spy();
        $attempts = 0;
        $reader = $this->reader();

        $result = $reader->read(function () use (&$attempts): array {
            $attempts++;

            throw new RuntimeException('Firestore remains unavailable.');
        }, []);

        $this->assertSame([], $result);
        $this->assertSame(2, $attempts);
        $this->assertSame(
            'Data Firebase belum dapat dimuat. Refresh halaman untuk mencoba lagi.',
            session('firebase_read_warning')
        );
    }

    private function reader(): object
    {
        return new class
        {
            use ReadsFirebaseData;

            public function read(callable $callback, mixed $fallback): mixed
            {
                return $this->attempt($callback, $fallback);
            }
        };
    }
}
