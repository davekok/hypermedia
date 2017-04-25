<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Activity,
	ActivityCache,
	Cache,
	CacheUnit,
	InstanceFactory,
	Journal,
	JournalRepository,
	StateFactory,
	UML,
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
	public function testCreateUnit()
	{
		$unit = (new UnitFactory(new AnnotationReader))->createUnitFromSource('TestUnit1', __DIR__.'/TestUnit1/');
		$this->assertEquals("TestUnit1", $unit->getName(), "unit name");
		$this->assertEquals(["Tests\\Sturdy\\Activity\\TestUnit1\\Activity1"], $unit->getClasses(), "classes");
		$this->assertEquals([], $unit->getDimensions(), "dimensions");
		$this->assertEquals([
			'start' => [
				(object)['next'=>'Tests\Sturdy\Activity\TestUnit1\Activity1::action1','dimensions'=>[]],
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action1' => [
				(object)['next'=>'Tests\Sturdy\Activity\TestUnit1\Activity1::action2','dimensions'=>[]],
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action2' => [
				(object)[
					'next'=>[
						1 => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action3',
						2 => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action4',
						3 => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action6',
					],
					'dimensions'=>[]
				],
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action3' => [
				(object)['next'=>'Tests\Sturdy\Activity\TestUnit1\Activity1::action7','dimensions'=>[]],
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action4' => [
				(object)['next'=>'Tests\Sturdy\Activity\TestUnit1\Activity1::action5','dimensions'=>[]]
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action5' => [
				(object)['next'=>'Tests\Sturdy\Activity\TestUnit1\Activity1::action7','dimensions'=>[]]
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action6' => [
				(object)['next'=>'Tests\Sturdy\Activity\TestUnit1\Activity1::action7','dimensions'=>[]]
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action7' => [
				(object)['next'=>'Tests\Sturdy\Activity\TestUnit1\Activity1::action8','dimensions'=>[]]
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action8' => [
				(object)['next'=>'Tests\Sturdy\Activity\TestUnit1\Activity1::action9','dimensions'=>[]]
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action9' => [
				(object)[
					'next'=>[
						'true' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action8',
						'false' => 'Tests\Sturdy\Activity\TestUnit1\Activity1::action10',
					],
					'dimensions'=>[]
				],
			],
			'Tests\Sturdy\Activity\TestUnit1\Activity1::action10' => [
				(object)[
					'next'=>null,
					'dimensions'=>[]
				],
			]
		], $unit->getActions(), "actions");
	}

	public function testCreateUnitWithDimensions()
	{
		$unit = (new UnitFactory(new AnnotationReader))->createUnitFromSource('TestUnit2', __DIR__.'/TestUnit2/');
		$this->assertEquals("TestUnit2", $unit->getName(), "unit name");
		$this->assertEquals(["Tests\\Sturdy\\Activity\\TestUnit2\\Activity1"], $unit->getClasses(), "classes");
		$this->assertEquals(["route", "role"], $unit->getDimensions(), "dimensions");
	}

	public function testActivity()
	{
		$prophet = new Prophet;

		$cache = $prophet->prophesize();
		$cache->willImplement(ActivityCache::class);
		$cache->getActivityActions('TestUnit1', [])
			->shouldBeCalledTimes(1)
			->willReturn([
				"start"=>TestUnit1\Activity1::class."::action1",
				TestUnit1\Activity1::class."::action1"=>TestUnit1\Activity1::class."::action2",
				TestUnit1\Activity1::class."::action2"=>[
					1=>TestUnit1\Activity1::class."::action3",
					2=>TestUnit1\Activity1::class."::action4",
					3=>TestUnit1\Activity1::class."::action6",
				],
				TestUnit1\Activity1::class."::action3"=>TestUnit1\Activity1::class."::action7",
				TestUnit1\Activity1::class."::action4"=>TestUnit1\Activity1::class."::action5",
				TestUnit1\Activity1::class."::action5"=>TestUnit1\Activity1::class."::action7",
				TestUnit1\Activity1::class."::action6"=>TestUnit1\Activity1::class."::action7",
				TestUnit1\Activity1::class."::action7"=>TestUnit1\Activity1::class."::action8",
				TestUnit1\Activity1::class."::action8"=>TestUnit1\Activity1::class."::action9",
				TestUnit1\Activity1::class."::action8"=>TestUnit1\Activity1::class."::action9",
				TestUnit1\Activity1::class."::action9"=>[
					"true"=>TestUnit1\Activity1::class."::action8",
					"false"=>TestUnit1\Activity1::class."::action10",
				],
				TestUnit1\Activity1::class."::action10"=>null,
			]);

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

		$activity = new Activity(
			$cache->reveal(),
			$journalRepository->reveal(),
			$stateFactory->reveal(),
			$instanceFactory->reveal());

		$activity = $activity->createJournal('TestUnit1');
		$activity->run();
		$prophet->checkPredictions();
		$this->assertEquals($activity->getUnit(), 'TestUnit1');
		$this->assertEquals($activity->getDimensions(), []);
		$this->assertFalse($activity->isRunning());
		$this->assertNull($activity->getReturn());
		$this->assertNull($activity->getErrorMessage());
		$this->assertEquals($activity->getCurrentAction(), "stop");
	}

	public function testCache()
	{
		$expectedActions = ["action1"=>"action2","action2"=>"action3","action3"=>null];

		$prophet = new Prophet;

		$unit = $prophet->prophesize();
		$unit->willImplement(CacheUnit::class);
		$unit->getName()->willReturn('testunit');
		$unit->getDimensions()->willReturn(["dim1", "dim2", "dim3"]);
		$unit->getActivities()->willReturn([(object)["dimensions"=>["dim1"=>1, "dim2"=>2, "dim3"=>3],"actions"=>$expectedActions]]);

		$cachepool = new ArrayCachePool;
		$cache = new Cache($cachepool);
		$cache->updateUnit($unit->reveal());

		$order = $cachepool->getItem("sturdy-activity|testunit.dimensions");
		$this->assertTrue($order->isHit(), "dimensions order is not stored");
		$this->assertEquals(json_decode($order->get()), ["dim1", "dim2", "dim3"]);

		$actions = $cachepool->getItem("sturdy-activity|testunit|".hash("sha256",json_encode(["dim1"=>1, "dim2"=>2, "dim3"=>3])));
		$this->assertTrue($actions->isHit(), "actions are not stored");
		$this->assertEquals($expectedActions, json_decode($actions->get(), true));
	}

	public function testUml()
	{
		$dimensions = ["dim1"=>1,"dim2"=>2];
		$actions = [
			"start"=>"TestClass::action1",
			"TestClass::action1"=>"TestClass::action2",
			"TestClass::action2"=>[
				1=>"TestClass::action3",
				2=>"TestClass::action4",
				3=>"TestClass::action6",
			],
			"TestClass::action3"=>"TestClass::action7",
			"TestClass::action4"=>"TestClass::action5",
			"TestClass::action5"=>"TestClass::action7",
			"TestClass::action6"=>"TestClass::action7",
			"TestClass::action7"=>"TestClass::action8",
			"TestClass::action8"=>"TestClass::action9",
			"TestClass::action8"=>"TestClass::action9",
			"TestClass::action9"=>[
				"true"=>"TestClass::action8",
				"false"=>"TestClass::action10",
			],
			"TestClass::action10"=>null,
		];

		$uml = new UML();
		$uml->setClassColor('TestClass', '#CCCCDD');
		$this->assertEquals($uml->generate($dimensions, $actions), <<<UML
@startuml
floating note left
	dim1: 1
	dim2: 2
end note
start
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
stop
@enduml

UML
		);
	}
}
