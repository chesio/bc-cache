<?php

namespace cli\progress;

class Bar
{
    /**
     * @param int    $increment The amount to increment by.
     * @param string $msg       The text to display next to the Notifier. (optional)
     */
    public function tick($increment = 1, $msg = null) {}

    public function finish() {}
}
