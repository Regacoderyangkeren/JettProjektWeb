<?php

namespace App\Http\Controllers\Concerns;

use Throwable;

trait ReadsFirebaseData
{
    private function attempt(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);
            usleep(150000);
        }

        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);
            session()->flash(
                'firebase_read_warning',
                'Data Firebase belum dapat dimuat. Refresh halaman untuk mencoba lagi.'
            );

            return $fallback;
        }
    }
}
