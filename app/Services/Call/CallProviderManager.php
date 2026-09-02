<?php

declare(strict_types=1);

namespace App\Services\Call;

use App\Enums\Call\CallProvider;
use App\Services\Call\Contracts\CallProviderInterface;
use App\Services\Call\Contracts\SyncsCallsInterface;
use App\Services\Call\Providers\Callyzer\CallyzerProvider;
use App\Services\Call\Providers\Exotel\ExotelProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves a provider adapter by name.
 *
 * The one place that knows which classes exist, so a job holding only a
 * provider string — which is all a queued payload can carry — can get back to
 * the right adapter. Adding a third provider is a line in this map plus its
 * adapter; nothing else in the call pipeline changes.
 */
class CallProviderManager
{
    /**
     * @var array<string, class-string<CallProviderInterface>>
     */
    protected const PROVIDERS = [
        'exotel' => ExotelProvider::class,
        'callyzer' => CallyzerProvider::class,
    ];

    public function __construct(
        protected Container $container,
    ) {}

    public function get(CallProvider|string $provider): CallProviderInterface
    {
        $key = $provider instanceof CallProvider ? $provider->value : $provider;

        if (! isset(self::PROVIDERS[$key])) {
            throw new InvalidArgumentException(sprintf('No call provider adapter is registered for "%s".', $key));
        }

        return $this->container->make(self::PROVIDERS[$key]);
    }

    public function has(CallProvider|string $provider): bool
    {
        return isset(self::PROVIDERS[$provider instanceof CallProvider ? $provider->value : $provider]);
    }

    /**
     * The adapter for a provider that can also be polled.
     *
     * Returns null rather than throwing for a provider that only pushes, so a
     * scheduled sync can skip it without special-casing Exotel by name.
     */
    public function syncable(CallProvider|string $provider): ?SyncsCallsInterface
    {
        $adapter = $this->get($provider);

        return $adapter instanceof SyncsCallsInterface ? $adapter : null;
    }

    /**
     * @return array<int, CallProviderInterface>
     */
    public function all(): array
    {
        return array_map(
            fn (string $class): CallProviderInterface => $this->container->make($class),
            array_values(self::PROVIDERS),
        );
    }

    /**
     * @return array<int, CallProviderInterface>
     */
    public function enabled(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (CallProviderInterface $adapter): bool => $adapter->isEnabled(),
        ));
    }
}
