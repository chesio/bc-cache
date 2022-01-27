<?php

namespace BlueChip\Cache\Tests\Unit\Cases\WarmUpQueue;

use BlueChip\Cache\Item;
use BlueChip\Cache\WarmUpQueue;

class PushPullFetchTest extends \BlueChip\Cache\Tests\Unit\TestCase
{
    public function testWarmUpQueue()
    {
        $items = [
            new Item('https://www.example.com/0', ''),
            new Item('https://www.example.com/1', ''),
            new Item('https://www.example.com/2', ''),
            new Item('https://www.example.com/3', ''),
        ];

        $extra = new Item('https://www.example.com/extra', '');

        // Prepare helper variables:
        [$first, $second, $third, $fourth] = $items;
        $size = \count($items);

        // Constructor
        $queue = new WarmUpQueue($items);

        // Check initial state: 0 items processed, all items remaining.
        $this->assertSame(0, $queue->getProcessedCount());
        $this->assertSame($size, $queue->getRemainingCount());
        $this->assertSame($size, $queue->getTotalCount());

        // Fetch 1st item.
        $this->assertSame($first, $queue->fetch());
        $this->assertSame(1, $queue->getProcessedCount());
        $this->assertSame($size - 1, $queue->getRemainingCount());
        $this->assertSame($size, $queue->getTotalCount());

        // Pull item that has not been processed yet.
        $queue->pull($fourth);
        $this->assertSame(2, $queue->getProcessedCount());
        $this->assertSame($size - 2, $queue->getRemainingCount());
        $this->assertSame($size, $queue->getTotalCount());

        // Pull item that has been processed already.
        $queue->pull($first);
        $this->assertSame(2, $queue->getProcessedCount());
        $this->assertSame($size - 2, $queue->getRemainingCount());
        $this->assertSame($size, $queue->getTotalCount());

        // Push item that has not been processed yet.
        $queue->push($third);
        $this->assertSame(2, $queue->getProcessedCount());
        $this->assertSame($size - 2, $queue->getRemainingCount());
        $this->assertSame($size, $queue->getTotalCount());

        // Push item that has been processed already.
        $queue->push($first);
        $this->assertSame(1, $queue->getProcessedCount());
        $this->assertSame($size - 1, $queue->getRemainingCount());
        $this->assertSame($size, $queue->getTotalCount());

        // Fetch all items that should be unprocessed at this point.
        $unprocessed = [$first, $second, $third];
        $this->assertContains($queue->fetch(), $unprocessed);
        $this->assertContains($queue->fetch(), $unprocessed);
        $this->assertContains($queue->fetch(), $unprocessed);

        // Queue should be empty at this point.
        $this->assertNull($queue->fetch());
        $this->assertTrue($queue->isEmpty());

        // Pull item that is not in queue yet - queue total count grows, but no items are unprocessed.
        $queue->pull($extra);
        $this->assertSame($queue->getTotalCount(), $queue->getProcessedCount());
        $this->assertSame(0, $queue->getRemainingCount());
        $this->assertSame($size + 1, $queue->getTotalCount());
    }
}
