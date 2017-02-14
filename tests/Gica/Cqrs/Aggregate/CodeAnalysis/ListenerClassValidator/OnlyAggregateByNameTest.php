<?php


namespace tests\Gica\Cqrs\Aggregate\CodeAnalysis\ListenerClassValidator;


use Gica\Cqrs\Aggregate\CodeAnalysis\ListenerClassValidator\OnlyAggregateByName;


class OnlyAggregateByNameTest extends \PHPUnit_Framework_TestCase
{

    public function testOnlyAggregateByNameTrue()
    {
        $sut = new OnlyAggregateByName();

        $acceptedClass = $this->getMockBuilder(\ReflectionClass::class)
            ->disableOriginalConstructor()
            ->getMock();
        $acceptedClass->method('getName')
            ->willReturn('SomeAggregate');

        $this->assertTrue($sut->isClassAccepted($acceptedClass));

    }

    public function testOnlyAggregateByNameFalse()
    {
        $sut = new OnlyAggregateByName();

        $acceptedClass = $this->getMockBuilder(\ReflectionClass::class)
            ->disableOriginalConstructor()
            ->getMock();
        $acceptedClass->method('getName')
            ->willReturn('SomeAggregateThatIsNotAccepted');

        $this->assertFalse($sut->isClassAccepted($acceptedClass));
    }
}