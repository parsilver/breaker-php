<?php

namespace Farzai\Breaker\Events;

/**
 * Event constants for circuit breaker events.
 */
class Events
{
    /**
     * Event fired when any state change occurs.
     */
    public const STATE_CHANGE = 'state_change';

    /**
     * Event fired when circuit transitions to open state.
     */
    public const OPEN = 'open';

    /**
     * Event fired when circuit transitions to closed state.
     */
    public const CLOSE = 'close';

    /**
     * Event fired when circuit transitions to half-open state.
     */
    public const HALF_OPEN = 'half_open';

    /**
     * Event fired when a service call succeeds.
     */
    public const SUCCESS = 'success';

    /**
     * Event fired when a service call fails.
     */
    public const FAILURE = 'failure';

    /**
     * Event fired when a fallback is successfully executed.
     */
    public const FALLBACK_SUCCESS = 'fallback_success';
}
