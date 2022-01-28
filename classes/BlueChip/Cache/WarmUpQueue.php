<?php

namespace BlueChip\Cache;

class WarmUpQueue
{
    /**
     * @var Item[] List of items that have been processed already
     */
    private $processed = [];

    /**
     * @var Item[] LIFO queue with items to be processed yet
     */
    private $waiting = [];


    /**
     * @param Item[] $items
     */
    public function __construct(array $items)
    {
        $this->waiting = \array_reverse($items);
    }


    /**
     * Fetch next item and mark it as processed.
     *
     * @return Item|null Next item from queue or null if there are no more items to process.
     */
    public function fetch(): ?Item
    {
        $item = \array_pop($this->waiting);

        if ($item) {
            \array_push($this->processed, $item);
        }

        return $item;
    }


    /**
     * @return int Number of processed items.
     */
    public function getProcessedCount(): int
    {
        return \count($this->processed);
    }


    /**
     * @return int Number of waiting (unprocessed) items.
     */
    public function getWaitingCount(): int
    {
        return \count($this->waiting);
    }


    /**
     * @return int Total number of items in queue (sum of processed and waiting items).
     */
    public function getTotalCount(): int
    {
        return \count($this->processed) + \count($this->waiting);
    }


    /**
     * @return array
     */
    public function getStats(): array
    {
        return [
            'processed' => $this->getProcessedCount(),
            'waiting' => $this->getWaitingCount(),
            'total' => $this->getTotalCount(),
        ];
    }


    /**
     * @return bool True if queue is empty (there are no more items to process), false otherwise.
     */
    public function isEmpty(): bool
    {
        return $this->waiting === [];
    }


    /**
     * Pulling an $item marks is as processed.
     *
     * @param Item $item
     */
    public function pull(Item $item): void
    {
        // Remove item from waiting items list if present.
        $index = \array_search($item, $this->waiting, false);
        if ($index !== false) {
            \array_splice($this->waiting, $index, 1);
        }

        // Add item to processed items list if *not* present.
        $index = \array_search($item, $this->processed, false);
        if ($index === false) {
            \array_push($this->processed, $item);
        }
    }

    /**
     * Pushing an $item marks it as waiting and puts it on top of the queue.
     *
     * @param Item $item
     */
    public function push(Item $item): void
    {
        // Remove item from processed items list if present.
        $index = \array_search($item, $this->processed, false);
        if ($index !== false) {
            \array_splice($this->processed, $index, 1);
        }

        // Add item to waiting items list if *not* present.
        $index = \array_search($item, $this->waiting, false);
        if ($index === false) {
            \array_push($this->waiting, $item);
        }
    }
}
