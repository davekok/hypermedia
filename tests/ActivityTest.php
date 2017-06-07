<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Activity,
	ActivityCache,
	InstanceFactory,
	Journal,
	JournalRepository,
	StateFactory
};
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument,
	Prophet
};

class ActivityTest extends TestCase
{
	public function testActivity()
	{
		$prophet = new Prophet;

		$cache = $prophet->prophesize();
		$cache->willImplement(ActivityCache::class);
		$cache->getActivity('TestUnit1', [])
			->shouldBeCalledTimes(1)
			->willReturn([
				"readonly"=>false,
				"actions"=>[
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
					TestUnit1\Activity1::class."::action10"=>false,
				]
			]);

		$journal = $prophet->prophesize();
		$journal->willImplement(Journal::class);
		$journal->getUnit()->willReturn(null);
		$journal->getDimensions()->willReturn(null);
		$journal->getState(Argument::type("int"))->willReturn(null);
		$journal->setState(Argument::type("int"), Argument::type(\stdClass::class))
			->shouldBeCalled()
			->will(function($args, $self)use($journal) {
				$journal->getState($args[0])->willReturn($args[1]);
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
		$journal->getCurrentAction(Argument::type("int"))->willReturn($currentAction);
		$journal->setCurrentAction(Argument::type("int"), Argument::type("string"))
			->will(function($args, $self)use($journal,$actions,&$currentAction) {
				[$branch, $action] = $args;
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
				$journal->getCurrentAction($branch)->willReturn($action);
				$currentAction = $action;
				return $self;
			});
		$journal->getErrorMessage(Argument::type("int"))->willReturn(null);
		$journal->setErrorMessage(Argument::type("int"), Argument::type('string'))
			->will(function($args, $self)use($journal) {
				[$branch, $errorMessage] = $args;
				$journal->getErrorMessage($branch)->willReturn($errorMessage);
				return $self;
			});
		$journal->getRunning(Argument::type("int"))->willReturn(false);
		$journal->setRunning(Argument::type("int"), Argument::type('bool'))
			->will(function($args, $self)use($journal) {
				[$branch, $running] = $args;
				$journal->getRunning($branch)->willReturn($running);
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

		$activity->load('TestUnit1', []);
		$activity->createJournal();
		$activity->run();
		$prophet->checkPredictions();
		$this->assertEquals('TestUnit1', $activity->getUnit());
		$this->assertEquals([], $activity->getDimensions());
		$this->assertFalse($activity->isRunning(0));
		$this->assertNull($activity->getReturn());
		$this->assertNull($activity->getErrorMessage(0));
		$this->assertEquals("stop", $activity->getCurrentAction(0));
	}
}
