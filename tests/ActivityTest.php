<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Activity,
	Cache,
	Diagrams,
	InstanceFactory,
	Journal,
	JournalRepository,
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
		$cache->updateUnit($unit);

		$order = self::$cache->getItem("sturdy-activity|TestUnit1.dimensions");
		$this->assertTrue($order->isHit(), "dimensions order is not stored");
		$this->assertEquals($order->get(), "[]");

		$actions = self::$cache->getItem("sturdy-activity|TestUnit1|");
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
		$journal->willImplement(Journal::class);
		$journal->getUnit()->willReturn(null);
		$journal->getDimensions()->willReturn(null);
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

		$actions = [
			"start",
			TestUnit1\Activity1::class."::action1",
			TestUnit1\Activity1::class."::action2",
			TestUnit1\Activity1::class."::action4",
			TestUnit1\Activity1::class."::action5",
			TestUnit1\Activity1::class."::action7",
			TestUnit1\Activity1::class."::action8",
			TestUnit1\Activity1::class."::action9",
			TestUnit1\Activity1::class."::action10",
			"stop",
		];
		$currentAction = null;
		$journal->getCurrentAction()->willReturn($currentAction);
		$journal->setCurrentAction(Argument::type("string"))
			->will(function($args, $self)use($journal,$actions,&$currentAction) {
				[$action] = $args;
				if ($currentAction !== null) {
					$ix = array_search($currentAction,$actions);
					$nextAction = $actions[$ix+1];
					if ($action != $nextAction) {
						throw new \Exception("expected $nextAction got $action");
					}
				} else {
					if ($action !== "start") {
						throw new \Exception("expected start got $action");
					}
				}
				$journal->getCurrentAction()->willReturn($action);
				$currentAction = $action;
				return $self;
			});
		$journal->getErrorMessage()->willReturn(null);
		$journal->setErrorMessage(Argument::type('string'))
			->will(function($args, $self)use($journal) {
				$journal->getErrorMessage()->willReturn($args[0]);
				return $self;
			});
		$journal->getRunning()->willReturn(false);
		$journal->setRunning(Argument::type('bool'))
			->will(function($args, $self)use($journal) {
				$journal->getRunning()->willReturn($args[0]);
				return $self;
			});

		$journalRepository = $prophet->prophesize();
		$journalRepository->willImplement(JournalRepository::class);
		$journalRepository->createJournal('TestUnit1', [])
			->shouldBeCalledTimes(1)
			->will(function($args)use($journal){
				$journal->getUnit()->willReturn($args[0]);
				$journal->getDimensions()->willReturn($args[1]);
				return $journal->reveal();
			});
		$journalRepository->saveJournal(Argument::type(Journal::class))
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
			->will(function(array $args)use($prophet){
				[$unit, $class] = $args;
				$instance = $prophet->prophesize();
				$instance->willExtend($class);
				$instance->action1(Argument::type(Activity::class))->willReturn(null);
				$instance->action2(Argument::type(Activity::class))->willReturn(2);
				$instance->action4(Argument::type(Activity::class))->willReturn(null);
				$instance->action5(Argument::type(Activity::class))->willReturn(null);
				$instance->action7(Argument::type(Activity::class))->willReturn(null);
				$instance->action8(Argument::type(Activity::class))->willReturn(null);
				$instance->action9(Argument::type(Activity::class))->willReturn(false);
				$instance->action10(Argument::type(Activity::class))->willReturn(null);
				return $instance;
			});

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
		$this->assertFalse($activity->isRunning());
		$this->assertNull($activity->getReturn());
		$this->assertNull($activity->getErrorMessage());
		$this->assertEquals($activity->getCurrentAction(), "stop");
	}
}
