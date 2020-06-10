<?php

namespace LeNats\Tests;

use LeNats\Events\Nats\Info;
use LeNats\Events\Nats\UndefinedMessageReceived;
use LeNats\Events\Nats\Ping;
use LeNats\Events\Nats\Pong;
use LeNats\Events\React\Data;
use LeNats\Exceptions\StreamException;
use LeNats\Listeners\MessageProcessorSubscriber;
use LeNats\Services\StringBuffer;
use LeNats\Subscription\Subscriber;

class MessageProcessorTest extends TestCase
{
    /**
     * @var StringBuffer
     */
    private $buffer;

    /**
     * @var MessageProcessorSubscriber
     */
    private $processor;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $subscriber = $this->getContainer()->get(Subscriber::class);
        $this->buffer = new StringBuffer();
        $this->buffer->clear();
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $this->processor = new MessageProcessorSubscriber($this->buffer, $subscriber);
        $this->processor->setDispatcher($dispatcher);
    }

    /** @test */
    public function itStoresMessageToBuffer(): void
    {
        $this->assertTrue($this->buffer->isEmpty());
        $this->buffer->append("PING\r\n");
        $this->assertFalse($this->buffer->isEmpty());

        $this->assertEquals("PING\r\n", $this->buffer->get());

        $this->buffer->append("PONG\r\n");
        $this->assertEquals("PING\r\nPONG\r\n", $this->buffer->get());

        $this->processor->processBuffer();
        $this->assertTrue($this->buffer->isEmpty());
    }

    /** @test */
    public function itProcessesCommands(): void
    {
        $this->markTestSkipped('Connection to SOME DSN HERE failed during DNS lookup: DNS error (fix!)');

        $this->assertEventHandled(Ping::class, function () {
            $this->processor->bufferize(new Data("PING\r\n"));
        });

        $this->assertEventHandled(Pong::class, function () {
            $this->processor->bufferize(new Data("PONG\r\n"));
        });

        $this->assertEventHandled(Info::class, function () {
            $this->processor->bufferize(new Data("INFO {'some':'info'}\r\n"));
        });
    }

    /** @test */
    public function itThrowsExceptionOnWrongCommand(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Message not handled');

        $this->processor->bufferize(new Data("WRONG message\r\n"));
    }

    /** @test */
    public function itProcessesMessage(): void
    {
        $this->assertEventHandled(UndefinedMessageReceived::class, function () {
            $this->processor->bufferize(new Data("MSG foo.bar 90 5\r\nhello"));
        });
    }
}
