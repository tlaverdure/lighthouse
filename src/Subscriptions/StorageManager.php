<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class StorageManager implements StoresSubscriptions
{
    /**
     * The cache key for topics.
     *
     * @var string
     */
    public const TOPIC_KEY = 'graphql.topic';

    /**
     * The cache key for subscribers.
     *
     * @var string
     */
    public const SUBSCRIBER_KEY = 'graphql.subscriber';

    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * @var \DateInterval|int|null
     */
    protected $ttl;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cache = $cacheManager->store(
            config('lighthouse.subscriptions.storage', 'redis')
        );
        $this->ttl = config('lighthouse.subscriptions.storage_ttl', null);
    }

    /**
     * Get subscriber by request.
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByRequest(array $input, array $headers): ?Subscriber
    {
        $channel = Arr::get($input, 'channel_name');

        return $channel
            ? $this->subscriberByChannel($channel)
            : null;
    }

    /**
     * Find subscriber by channel.
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByChannel(string $channel): ?Subscriber
    {
        $key = self::SUBSCRIBER_KEY.".{$channel}";

        return $this->cache->get($key);
    }

    /**
     * Get collection of subscribers by channel.
     *
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    public function subscribersByTopic(string $topic): Collection
    {
        $channelsJson = $this->cache->get(self::TOPIC_KEY.".{$topic}");
        if (! $channelsJson) {
            return new Collection;
        }

        $channels = json_decode($channelsJson, true);

        return (new Collection($channels))
            ->map(function (string $channel): ?Subscriber {
                return $this->subscriberByChannel($channel);
            })
            ->filter()
            ->values();
    }

    public function storeSubscriber(Subscriber $subscriber, string $topic): void
    {
        $topicKey = self::TOPIC_KEY.".{$topic}";
        $subscriberKey = self::SUBSCRIBER_KEY.".{$subscriber->channel}";

        $topicJson = $this->cache->get($topicKey);
        $topic = $topicJson
            ? json_decode($topicJson, true)
            : [];

        $topic[] = $subscriber->channel;

        if ($this->ttl === null) {
            $this->cache->forever($topicKey, json_encode($topic));
            $this->cache->forever($subscriberKey, $subscriber);
        } else {
            $this->cache->put($topicKey, json_encode($topic), $this->ttl);
            $this->cache->put($subscriberKey, $subscriber, $this->ttl);
        }
    }

    public function deleteSubscriber(string $channel): ?Subscriber
    {
        return $this->cache->pull(self::SUBSCRIBER_KEY.".{$channel}");
    }
}
