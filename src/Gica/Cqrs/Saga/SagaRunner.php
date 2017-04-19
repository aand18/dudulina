<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace Gica\Cqrs\Saga;


use Gica\CodeAnalysis\MethodListenerDiscovery;
use Gica\CodeAnalysis\MethodListenerDiscovery\ListenerClassValidator\AnyPhpClassIsAccepted;
use Gica\CodeAnalysis\MethodListenerDiscovery\ListenerMethod;
use Gica\CodeAnalysis\Shared\ClassSorter\ByConstructorDependencySorter;
use Gica\Cqrs\Command\CodeAnalysis\WriteSideEventHandlerDetector;
use Gica\Cqrs\Event\EventWithMetaData;
use Gica\Cqrs\EventStore;
use Gica\Cqrs\Saga\SagaEventTrackerRepository\ConcurentEventProcessingException;
use Gica\Cqrs\Saga\SagaRunner\EventProcessingHasStalled;
use Psr\Log\LoggerInterface;

class SagaRunner
{

    /**
     * @var EventStore
     */
    private $eventStore;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var SagaEventTrackerRepository
     */
    private $sagaRepository;

    public function __construct(
        EventStore $eventStore,
        LoggerInterface $logger,
        SagaEventTrackerRepository $sagaRepository
    )
    {
        $this->eventStore = $eventStore;
        $this->logger = $logger;
        $this->sagaRepository = $sagaRepository;
    }

    public function feedSagaWithEvents($saga, ?int $afterSequence = null)
    {
        $discoverer = new MethodListenerDiscovery(
            new WriteSideEventHandlerDetector(),
            new AnyPhpClassIsAccepted(),
            new ByConstructorDependencySorter()
        );

        $allMethods = $discoverer->findListenerMethodsInClass(get_class($saga));

        $eventClasses = $this->getEventClassesFromMethods($allMethods);

        $this->logger->info(print_r($eventClasses, true));
        $this->logger->info("loading events...");

        $allEvents = $this->eventStore->loadEventsByClassNames($eventClasses);

        if (null !== $afterSequence) {
            $allEvents->afterSequence($afterSequence);
        }

        $this->logger->info("processing events...");

        foreach ($allEvents as $eventWithMetadata) {
            /** @var EventWithMetaData $eventWithMetadata */
            $methods = $this->findMethodsByEventClass(get_class($eventWithMetadata->getEvent()), $allMethods);
            $metaData = $eventWithMetadata->getMetaData();

            $eventOrder = new EventOrder($metaData->getSequence(), $metaData->getIndex());
            
            foreach ($methods as $method) {

                try {
                    if ($this->sagaRepository->isEventProcessingAlreadyStarted(get_class($saga), $eventOrder)) {
                        if (!$this->sagaRepository->isEventProcessingAlreadyEnded(get_class($saga), $eventOrder)) {
                            throw new EventProcessingHasStalled($eventWithMetadata);
                        }
                    } else {
                        $this->sagaRepository->startProcessingEventBySaga(get_class($saga), $eventOrder);
                        call_user_func([$saga, $method->getMethodName()], $eventWithMetadata->getEvent(), $eventWithMetadata->getMetaData());
                        $this->sagaRepository->endProcessingEventBySaga(get_class($saga), $eventOrder);
                    }
                } catch (ConcurentEventProcessingException $exception) {
                    $this->logger->info("concurent event processing exception, skipping...");
                    continue;
                }
            }
        }
    }

    /**
     * @param ListenerMethod[] $methods
     * @return array
     */
    private function getEventClassesFromMethods($methods)
    {
        $eventClasses = [];
        foreach ($methods as $listenerMethod) {
            $eventClasses[] = $listenerMethod->getEventClassName();
        }

        return $eventClasses;
    }

    /**
     * @param string $eventClass
     * @param ListenerMethod[] $allMethods
     * @return ListenerMethod[]
     */
    private function findMethodsByEventClass(string $eventClass, $allMethods)
    {
        $result = [];

        foreach ($allMethods as $listenerMethod) {
            if ($listenerMethod->getEventClassName() == $eventClass) {
                $result[] = $listenerMethod;
            }
        }

        return $result;
    }
}