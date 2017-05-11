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
		$cache->hasActivity('TestUnit1', [])->shouldBeCalledTimes(1)->willReturn(true);
		$cache->getActivity('TestUnit1', [])
			->shouldBeCalledTimes(1)
			->willReturn([
				"const"=>false,
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
					TestUnit1\Activity1::class."::action10"=>null,
				]
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

		$this->assertTrue($activity->hasActivity('TestUnit1', []));

		$activity->createJournal('TestUnit1');
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
