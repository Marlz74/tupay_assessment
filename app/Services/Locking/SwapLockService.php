<?php

namespace App\Services\Locking;

use App\Exceptions\ConcurrentSwapConflictException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class SwapLockService
{
    /** @var list<Lock> */
    private array $locks = [];

    /**
     * Acquire user and wallet locks in alphabetical id order.
     *
     * @param  list<string>  $walletIds
     */
    public function acquire(string $userId, array $walletIds): void
    {
        $seconds = (int) config('tupay.swap.lock_seconds', 10);
        $wait = (int) config('tupay.swap.lock_wait_seconds', 3);

        $walletIds = array_values(array_unique($walletIds));
        sort($walletIds, SORT_STRING);

        $keys = array_merge(
            ["swap:user:{$userId}"],
            array_map(static fn (string $id): string => "swap:wallet:{$id}", $walletIds),
        );

        try {
            foreach ($keys as $key) {
                $lock = Cache::lock($key, $seconds);
                if (! $lock->block($wait)) {
                    throw new ConcurrentSwapConflictException('A transaction is already in progress. Please try again shortly.');
                }
                $this->locks[] = $lock;
            }
        } catch (ConcurrentSwapConflictException $e) {
            $this->release();
            throw $e;
        }
    }

    public function release(): void
    {
        while ($lock = array_pop($this->locks)) {
            try {
                $lock->release();
            } catch (\Throwable) {

            }
        }
    }
}
