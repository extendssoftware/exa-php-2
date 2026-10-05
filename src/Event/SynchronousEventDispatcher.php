<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Event;

use Override;
use ExtendsSoftware\ExaPHP\Event\Exception\DuplicateEventRegistrationException;
use ExtendsSoftware\ExaPHP\Event\Exception\InvalidEventRegistrationException;
use ExtendsSoftware\ExaPHP\Event\Listener\EventListener;
use ReflectionClass;
use Throwable;

use function array_is_list;
use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Dispatches events immediately to listeners registered for their exact class.
 *
 * Registrations are fixed after construction and listener instances are reused. Class aliases and differently cased
 * names are normalized. Registration validates runtime contracts, not listener PHPDoc generic bindings.
 * Empty lists are valid. Repeated listener instances are invoked once per list entry.
 */
final readonly class SynchronousEventDispatcher implements EventDispatcher
{
    /**
     * Ordered listener lists indexed by canonical event class.
     *
     * @var array<class-string<Event>, list<EventListener>>
     */
    private array $listeners;

    /**
     * Creates a dispatcher with validated event listener lists.
     *
     * @param array<class-string<Event>, list<EventListener>> $listeners Listener lists indexed by concrete event class.
     *
     * @throws InvalidEventRegistrationException When a key is not a concrete Event class or a listener list is invalid.
     * @throws DuplicateEventRegistrationException When multiple keys identify the same canonical event class.
     */
    public function __construct(array $listeners)
    {
        $registrations = [];
        foreach ($listeners as $eventClass => $eventListeners) {
            if (!is_string($eventClass) || $eventClass === '') {
                throw new InvalidEventRegistrationException('Event registration keys must be non-empty class names.');
            }

            try {
                $class = new ReflectionClass($eventClass);
            } catch (Throwable $exception) {
                throw new InvalidEventRegistrationException(
                    sprintf('Could not inspect registered event class "%s".', $eventClass),
                    0,
                    $exception,
                );
            }

            if ($class->isInterface() || $class->isAbstract() || !$class->implementsInterface(Event::class)) {
                throw new InvalidEventRegistrationException(
                    sprintf('Registered class "%s" must be a concrete Event.', $eventClass),
                );
            }

            if (!is_array($eventListeners) || !array_is_list($eventListeners)) {
                throw new InvalidEventRegistrationException(
                    sprintf('Listeners for event "%s" must be a list.', $eventClass),
                );
            }

            $validatedListeners = [];
            foreach ($eventListeners as $index => $listener) {
                if (!$listener instanceof EventListener) {
                    throw new InvalidEventRegistrationException(
                        sprintf(
                            'Listener %d for event "%s" must implement EventListener, %s given.',
                            $index,
                            $eventClass,
                            get_debug_type($listener),
                        ),
                    );
                }

                $validatedListeners[] = $listener;
            }

            $name = $class->getName();
            if (array_key_exists($name, $registrations)) {
                throw new DuplicateEventRegistrationException(
                    sprintf('A listener list is already registered for event "%s".', $name),
                );
            }

            $registrations[$name] = $validatedListeners;
        }

        $this->listeners = $registrations;
    }

    /**
     * Invokes listeners for the event's exact class in registration order.
     *
     * Parent classes and interfaces are not considered. The original event object is passed to the listener.
     * Events without listeners are ignored. Listener failures propagate unchanged and stop this dispatch.
     *
     * @param Event $event The event to dispatch.
     *
     * @return void
     *
     * @throws Throwable When the listener fails, propagated unchanged.
     */
    #[Override]
    public function dispatch(Event $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener->handle($event);
        }
    }
}
