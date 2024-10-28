<?php

declare(strict_types=1);

namespace BlueChip\Cache;

enum CrawlingState
{
    /**
     * There are no items left in the warm-up queue.
     */
    case FINISHED;

    /**
     * There are items left in the warm-up queue and next crawler run is scheduled in the past (= is due).
     */
    case RUNNING;

    /**
     * There are items left in the warm-up queue and next crawler run is scheduled in the future (= is planned).
     */
    case SCHEDULED;

    /**
     * There are items left in the warm-up queue, but no next crawler run is scheduled.
     */
    case STALLED;
}
