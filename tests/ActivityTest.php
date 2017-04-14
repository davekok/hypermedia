<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Activity,
	Cache,
	Diagrams,
	Entity,
	InstanceFactory,
	Repository,
	State,
	StateFactory,
	UnitFactory
};
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Annotations\AnnotationReader;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Prophecy\{
	Argument,
	Prophet
};

class ActivityTest extends TestCase
{
	private static $cache;

	public function __construct()
	{
		if (self::$cache === null) {
			self::$cache = new ArrayCachePool;
		}
	}

	public function testCreateUnit()
	{
		$expectedActions = [
			'start' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action1',
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action1' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action2',
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action2' => [
				1 => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action3',
				2 => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action4',
				3 => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action6',
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action3' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action7',
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action4' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action5',
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action5' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action7',
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action6' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action7',
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action7' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action8',
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action8' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action9',
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action9' => [
				'true' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action8',
				'false' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action10',
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action10' => NULL,
		];

		$unit = (new UnitFactory(new AnnotationReader))->createUnitFromSource('TestUnit1', __DIR__.'/TestUnit1/');
		$this->assertEquals($unit->getName(), "TestUnit1");
		$this->assertEquals($unit->getClasses(), ["Tests\\Sturdy\\Activity\\TestUnit1\\Activity1"]);
		$this->assertEquals($unit->getDimensions(), []);
		$this->assertEquals($unit->getActions(), ["" => $expectedActions]);


		$cache = new Cache(self::$cache);
		$cache->updateActivities($unit);

		$order = self::$cache->getItem("Sturdy|Activity|TestUnit1.dimensions");
		$this->assertTrue($order->isHit(), "dimensions order is not stored");
		$this->assertEquals($order->get(), "[]");

		$actions = self::$cache->getItem("Sturdy|Activity|TestUnit1|");
		$this->assertTrue($actions->isHit(), "actions are not stored");
		$this->assertEquals(json_decode($actions->get(), true), $expectedActions);

		$diagrams = new Diagrams();
		$diagrams->setUnit($unit);
		$diagrams->setClassColor('Tests\Sturdy\Activity\TestUnit1\Activity1', '#CCCCDD');
		$uml = "";
		foreach ($diagrams->generate() as $line) {
			if ($line[0] == "\0") {
				$this->assertEquals(substr($line,1), 'activity.uml');
			} else {
				$uml.= $line;
			}
		}
		$this->assertEquals($uml, <<<UML
@startuml
:start;
#CCCCDD:action1|
#CCCCDD:action2|
if (r) then (1)
	#CCCCDD:action3|
elseif (r) then (2)
	#CCCCDD:action4|
	#CCCCDD:action5|
else (3)
	#CCCCDD:action6|
endif
#CCCCDD:action7|
repeat
	#CCCCDD:action8|
	#CCCCDD:action9|
repeat while (r = true)
#CCCCDD:action10|
:stop;
@enduml

UML
		);
	}

	public function testActivity()
	{
		$prophet = new Prophet;

		$journal = $prophet->prophesize();
		$journal->willImplement(Entity\Journal::class);
		$journal->getUnit()->willReturn(null);
		$journal->setUnit('TestUnit1')
			->shouldBeCalled()
			->will(function($args, $self)use($journal) {
				$journal->getUnit()->willReturn('TestUnit1');
				return $self;
			});
		$journal->getDimensions()->willReturn(null);
		$journal->setDimensions([])
			->shouldBeCalled()
			->will(function($args, $self)use($journal) {
				$journal->getDimensions()->willReturn([]);
				return $self;
			});
		$journal->getState()->willReturn(null);
		$journal->setState(Argument::type(\stdClass::class))
			->shouldBeCalled()
			->will(function($args, $self)use($journal) {
				$journal->getState()->willReturn($args[0]);
				return $self;
			});
		$journal->getReturn()->willReturn(null);
		$journal->setReturn(Argument::any())
			->will(function($args, $self)use($journal) {
				$journal->getReturn()->willReturn($args[0]);
				return $self;
			});
		$journal->getCurrentAction()->willReturn(null);
		$journal->setCurrentAction(Argument::type('string'))
			->will(function($args, $self)use($journal) {
				$journal->getCurrentAction()->willReturn($args[0]);
				return $self;
			});
		$journal->getErrorMessage()->willReturn(null);
		$journal->setErrorMessage(Argument::type('string'))
			->will(function($args, $self)use($journal) {
				$journal->getErrorMessage()->willReturn($args[0]);
				return $self;
			});

		$journalRepository = $prophet->prophesize();
		$journalRepository->willImplement(Repository\JournalRepository::class);
		$journalRepository->createJournal('TestUnit1', [], Argument::type(\stdClass::class))
			->shouldBeCalledTimes(1)
			->will(function($args)use($journal) {
				$j = $journal->reveal();
				$j->setUnit($args[0]);
				$j->setDimensions($args[1]);
				$j->setState($args[2]);
				$j->setCurrentAction("start");
				return $j;
			});
		$journalRepository->saveJournal(Argument::type(Entity\Journal::class))
			->shouldBeCalled();

		$stateFactory = $prophet->prophesize();
		$stateFactory->willImplement(StateFactory::class);
		$stateFactory->createState('TestUnit1', [])
			->shouldBeCalledTimes(1)
			->will(function($args){
				return new \stdClass;
			});

		$instanceFactory = $prophet->prophesize();
		$instanceFactory->willImplement(InstanceFactory::class);
		$instanceFactory->createInstance('TestUnit1', TestUnit1\Activity1::class)
			->shouldBeCalledTimes(1)
			->willReturn(new TestUnit1\Activity1);

		$activityFactory = new Activity(
			new Cache(self::$cache),
			$journalRepository->reveal(),
			$stateFactory->reveal(),
			$instanceFactory->reveal());

		$activity = $activityFactory->createActivity('TestUnit1');
		$activity->run();
		$prophet->checkPredictions();
		$this->assertEquals($activity->getUnit(), 'TestUnit1');
		$this->assertEquals($activity->getDimensions(), []);
		$this->assertNull($activity->getReturn());
		echo "\n".$activity->getJournal()->getErrorMessage()."\n";
		$this->assertEquals($activity->getJournal()->getCurrentAction(), "stop");
	}
}
