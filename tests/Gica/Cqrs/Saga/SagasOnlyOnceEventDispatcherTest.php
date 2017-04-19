<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace tests\Gica\Cqrs\Saga;

use Gica\Cqrs\Event\EventSubscriber;
use Gica\Cqrs\Event\EventWithMetaData;
use Gica\Cqrs\Event\MetaData;
use Gica\Cqrs\Saga\SagaEventTrackerRepository;
use Gica\Cqrs\Saga\SagaEventTrackerRepository\ConcurentEventProcessingException;
use Gica\Cqrs\Saga\SagaRunner\EventProcessingHasStalled;
use Gica\Cqrs\Saga\SagasOnlyOnceEventDispatcher;

class SagasOnlyOnceEventDispatcherTest extends \PHPUnit_Framework_TestCase
{

    const SAGA_ID = 'sagaId';

    public function test_dispatchEvent_not_isEventProcessingAlreadyStarted()
    {
        $metadata = $this->getMockBuilder(MetaData::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metadata->method('getSequence')
            ->willReturn(1);

        $metadata->method('getIndex')
            ->willReturn(2);

        /** @var MetaData $metadata */
        $eventWithMetadata = new EventWithMetaData('event', $metadata);

        $saga = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['someListenerMethod'])
            ->getMock();

        $saga->expects($this->once())
            ->method('someListenerMethod');

        $subscriber = $this->getMockBuilder(EventSubscriber::class)
            ->getMock();

        $subscriber->method('getListenersForEvent')
            ->willReturn([[$saga, 'someListenerMethod']]);

        $repository = $this->getMockBuilder(SagaEventTrackerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository->method('isEventProcessingAlreadyStarted')
            ->willReturn(false);

        $repository->method('startProcessingEventBySaga');

        $repository->method('endProcessingEventBySaga');


        /** @var SagaEventTrackerRepository $repository */
        /** @var EventSubscriber $subscriber */
        $sut = new SagasOnlyOnceEventDispatcher($repository, $subscriber);

        /** @var EventWithMetaData $eventWithMetadata */
        $sut->dispatchEvent($eventWithMetadata);
    }

    public function test_dispatchEvent_ConcurentModificationException()
    {
        $metadata = $this->getMockBuilder(MetaData::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metadata->method('getSequence')
            ->willReturn(1);

        $metadata->method('getIndex')
            ->willReturn(2);

        /** @var MetaData $metadata */
        $eventWithMetadata = new EventWithMetaData('event', $metadata);

        $saga = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['someListenerMethod'])
            ->getMock();

        $saga->expects($this->never())
            ->method('someListenerMethod');

        $subscriber = $this->getMockBuilder(EventSubscriber::class)
            ->getMock();

        $subscriber->method('getListenersForEvent')
            ->willReturn([[$saga, 'someListenerMethod']]);

        $repository = $this->getMockBuilder(SagaEventTrackerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository->method('isEventProcessingAlreadyStarted')
            ->with(get_class($saga))
            ->willReturn(false);

        $repository->method('startProcessingEventBySaga')
            ->willThrowException(new ConcurentEventProcessingException());

        $repository
            ->expects($this->never())
            ->method('endProcessingEventBySaga');


        /** @var SagaEventTrackerRepository $repository */
        /** @var EventSubscriber $subscriber */
        $sut = new SagasOnlyOnceEventDispatcher($repository, $subscriber);

        /** @var EventWithMetaData $eventWithMetadata */
        $sut->dispatchEvent($eventWithMetadata);
    }

    public function test_dispatchEvent_already_parsed()
    {
        $metadata = $this->getMockBuilder(MetaData::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metadata->method('getSequence')
            ->willReturn(1);

        $metadata->method('getIndex')
            ->willReturn(1);

        /** @var MetaData $metadata */
        $eventWithMetadata = new EventWithMetaData('event', $metadata);

        $saga = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['someListenerMethod'])
            ->getMock();

        $saga->expects($this->never())
            ->method('someListenerMethod');

        $subscriber = $this->getMockBuilder(EventSubscriber::class)
            ->getMock();

        $subscriber->method('getListenersForEvent')
            ->willReturn([[$saga, 'someListenerMethod']]);

        $repository = $this->getMockBuilder(SagaEventTrackerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository->method('isEventProcessingAlreadyStarted')
            ->with(get_class($saga))
            ->willReturn(true);

        $repository->method('isEventProcessingAlreadyEnded')
            ->with(get_class($saga))
            ->willReturn(true);

        $repository->expects($this->never())
            ->method('startProcessingEventBySaga');

        $repository->expects($this->never())
            ->method('endProcessingEventBySaga');

        /** @var SagaEventTrackerRepository $repository */
        /** @var EventSubscriber $subscriber */
        $sut = new SagasOnlyOnceEventDispatcher($repository, $subscriber);

        /** @var EventWithMetaData $eventWithMetadata */
        $sut->dispatchEvent($eventWithMetadata);
    }
}
