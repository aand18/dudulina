<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace tests\Gica\Cqrs\Command\CommandTesterWithExplanation\CommandDispatcherCanExecuteCommandCommandNotAcceptedTest;


use Gica\Cqrs\Aggregate\AggregateRepository;
use Gica\Cqrs\Command;
use Gica\Cqrs\Command\CommandApplier;
use Gica\Cqrs\Command\CommandSubscriber;
use Gica\Cqrs\Command\CommandTester\DefaultCommandTesterWithExplanation;
use Gica\Cqrs\Command\MetadataFactory\DefaultMetadataWrapper;
use Gica\Cqrs\Event\EventsApplier\EventsApplierOnAggregate;
use Gica\Cqrs\Event\MetadataFactory\DefaultMetadataFactory;
use Gica\Cqrs\EventStore\InMemory\InMemoryEventStore;

class DefaultCommandTesterWithExplanationTest extends \PHPUnit_Framework_TestCase
{

    const AGGREGATE_ID = 123;

    /** @var Command */
    private $command;

    protected function setUp()
    {
        $this->command = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->command->expects($this->any())
            ->method('getAggregateId')
            ->willReturn(self::AGGREGATE_ID);
    }

    public function test_canExecuteCommandNotAcceptedCommand()
    {
        $aggregateId = self::AGGREGATE_ID;
        $aggregateClass = Aggregate1::class;

        $commandSubscriber = $this->mockCommandSubscriber();

        $eventStore = new InMemoryEventStore($aggregateClass, $aggregateId);

        $eventsApplierOnAggregate = new EventsApplierOnAggregate();

        $commandApplier = new CommandApplier();

        $aggregateRepository = new AggregateRepository($eventStore, $eventsApplierOnAggregate);

        Aggregate1::$expectedCommand = $this->command;
        Aggregate1::$throwedException = new \Exception("Command not accepted");

        $commandDispatcher = new DefaultCommandTesterWithExplanation(
            $commandSubscriber,
            $commandApplier,
            $aggregateRepository,
            $eventsApplierOnAggregate,
            new DefaultMetadataFactory(),
            new DefaultMetadataWrapper()
        );

        $errors = $commandDispatcher->whyCantExecuteCommand($this->command);

        $this->assertCount(1, $errors);

        $this->assertSame(Aggregate1::$throwedException, $errors[0]);
    }

    private function mockCommandSubscriber(): CommandSubscriber
    {
        $commandSubscriber = $this->getMockBuilder(CommandSubscriber::class)
            ->getMock();

        $commandSubscriber->expects($this->any())
            ->method('getHandlerForCommand')
            ->with($this->equalTo($this->command))
            ->willReturn(new Command\ValueObject\CommandHandlerDescriptor(
                Aggregate1::class,
                'handleCommand1'
            ));

        /** @var CommandSubscriber $commandSubscriber */
        return $commandSubscriber;
    }
}

class Command1 implements \Gica\Cqrs\Command
{
    /**
     * @var
     */
    private $aggregateId;

    public function __construct(
        $aggregateId
    )
    {
        $this->aggregateId = $aggregateId;
    }

    public function getAggregateId()
    {
        return $this->aggregateId;
    }
}

class Aggregate1
{

    /**
     * @var Command
     */
    public static $expectedCommand;

    /**
     * @var \Exception
     */
    public static $throwedException;

    public function handleCommand1($command1)
    {
        throw self::$throwedException;

        /** @noinspection PhpUnreachableStatementInspection */
        yield "event";
    }
}