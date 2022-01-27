<?php

namespace BlueChip\Cache;

class WarmUpQueue
{
    /**
     * @var Item[]
     */
    private $items = [];

    /**
     * @var int
     */
    private $current = 0;

    /**
     * @param Item[] $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Fetch next item and mark it as processed.
     *
     * @return Item|null Next item from queue or null if there are no more items to process.
     */
    public function fetch(): ?Item
    {
        // Post-increment the pointer, but only if queue is not empty yet.
        return $this->isEmpty() ? null : $this->items[$this->current++];
    }

    /**
     * @return int Number of processed items.
     */
    public function getProcessedCount(): int
    {
        return $this->current;
    }

    /**
     * @return int Number of unprocessed items.
     */
    public function getRemainingCount(): int
    {
        return $this->getTotalCount() - $this->getProcessedCount();
    }

    /**
     * @return int Total number of items in queue (sum of processed and unprocessed items).
     */
    public function getTotalCount(): int
    {
        return \count($this->items);
    }

    /**
     * @return array
     */
    public function getStats(): array
    {
        return [
            'processed' => $this->getProcessedCount(),
            'remaining' => $this->getRemainingCount(),
            'total' => $this->getTotalCount(),
        ];
    }

    /**
     * @return bool True if queue is empty (there are no more items to process), false otherwise.
     */
    public function isEmpty(): bool
    {
        return $this->getProcessedCount() === $this->getTotalCount();
    }

    /**
     * Pulling an $item moves it at the beginning of processed items.
     *
     * @param Item $item
     */
    public function pull(Item $item): void
    {
        $index = \array_search($item, $this->items, false);

        if ($index !== false) {
            // Item is in a queue already, remove it from original position.
            \array_splice($this->items, $index, 1);
        }

        // Push item to the beginning of the array.
        \array_unshift($this->items, $item);

        if (($index === false) || ($index >= $this->current)) {
            // Item either wasn't in queue yet or has not been processed yet, so correct the pointer.
            $this->current += 1;
        }
    }

    /**
     * Pushing an $item moves it to the end of unprocessed items.
     *
     * @param Item $item
     */
    public function push(Item $item): void
    {
        $index = \array_search($item, $this->items, false);

        // Append item to end of the array.
        \array_push($this->items, $item);

        if ($index !== false) {
            // Item was in a queue already, remove it from original position.
            \array_splice($this->items, $index, 1);

            if ($index < $this->current) {
                // Item has been processed already, shift the pointer one item back.
                $this->current -= 1;
            }
        }
    }
}
