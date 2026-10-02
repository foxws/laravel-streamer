<?php

declare(strict_types=1);

namespace Foxws\Streamer\Filesystem;

use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaCollection
{
    use ForwardsCalls;

    /** @var Collection<int, Media> */
    protected Collection $items;

    /**
     * @param  array<int, Media>  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = Collection::make($items);
    }

    /**
     * @param  array<int, Media>  $items
     */
    public static function make(array $items = []): self
    {
        return new self($items);
    }

    /**
     * Returns an array with all locals paths of the Media items.
     *
     * @return array<int, string>
     */
    public function getLocalPaths(): array
    {
        return $this->items->map->getLocalPath()->all();
    }

    /**
     * Find a Media object by its path.
     */
    public function findByPath(string $path): ?Media
    {
        return $this->items->first(function (Media $media) use ($path) {
            return $media->getPath() === $path;
        });
    }

    /**
     * Get the first item from the collection.
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        return $this->items->first($callback, $default);
    }

    /**
     * Get the last item from the collection.
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        return $this->items->last($callback, $default);
    }

    /**
     * Push an item onto the end of the collection.
     */
    public function push(mixed $value): self
    {
        $this->items->push($value);

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function collection(): Collection
    {
        return $this->items;
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->collection(), $method, $parameters);
    }
}
