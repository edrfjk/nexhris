<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when a reviewer acts on a leave form whose stage was already decided
 * — a double click, a second tab, or another reviewer at the same stage who
 * got there first. Nothing is written; the reviewer is told what happened
 * instead of being shown an error page.
 */
class LeaveAlreadyDecided extends RuntimeException
{
}
