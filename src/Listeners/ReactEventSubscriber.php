<?php

namespace LeNats\Listeners;

use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\Pong;
use LeNats\Events\React\Close;
use LeNats\Events\React\End;
use LeNats\Events\React\Error;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\NatsException;
use LeNats\Exceptions\StreamException;
use LeNats\Exceptions\SubscriptionNotFoundException;
use LeNats\Services\Connection;
use LeNats\Subscription\CloseConnection;
use LeNats\Subscription\Subscriber;
use LeNats\Subscription\Subscription;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Inbox;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReactEventSubscriber implements EventSubscriberInterface, EventDispatcherAwareInterface
{
    use Dispatcherable;
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var Subscriber
     */
    private $subscriber;

    /**
     * @var CloseConnection
     */
    private $closeConnection;

    public function __construct(
        Connection $connection,
        Subscriber $subscriber,
        CloseConnection $closeConnection,
        ?LoggerInterface $logger = null
) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->closeConnection = $closeConnection;
        $this->subscriber = $subscriber;
    }

    /**
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Error::class => 'onError',
            Close::class => 'onClose',
            End::class   => 'onEnd',
            Pong::class  => 'onPong',
        ];
    }

    /**
     * @param Close $event
     */
    public function onClose(Close $event): void
    {
        $event->stopPropagation();

        $this->verboseLog('CLOSE. ' . $event->message);

        $this->connection->close();

        $this->connection->stopAll();
    }

    /**
     * @param  End                           $event
     * @throws ConnectionException
     * @throws StreamException
     * @throws SubscriptionNotFoundException
     */
    public function onEnd(End $event): void
    {
        $event->stopPropagation();

        if ($this->connection->isConnected() && !$this->connection->isShutdown()) {
            $this->connection->setShutdown(true);
            $this->subscriber->unsubscribeAll();

            $subscription = new Subscription(Inbox::newInbox());
            $subscription->setTimeout($this->connection->getConfig()->getWriteTimeout());
            $this->closeConnection->subscribe($subscription);

            $this->verboseLog('Shutdown. Unsubscribed and closed connection');
        }

        $this->verboseLog('END. ' . $event->message);
    }

    /**
     * @param  Error         $event
     * @throws NatsException
     */
    public function onError(Error $event): void
    {
        $this->connection->close();

        $this->connection->stopAll();

        $this->verboseLog('ERROR. ' . $event->error);

        throw new NatsException($event->error);
    }

    public function onPong(Pong $event): void
    {
        $this->verboseLog('PONG handled');
    }

    private function verboseLog(string $message): void
    {
        if ($this->logger) {
            $this->logger->debug($message);
        }
    }
}
