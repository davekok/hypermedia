<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Activity,
	ActivityCache,
	InstanceFactory,
	Journal,
	JournalBranch,
	JournalRepository,
	State,
	StateFactory
};
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument,
	Prophet
};
use Throwable;

class ActivityTest extends TestCase
{
	public function testActivity()
	{
		$prophet = new Prophet;

		$cache = $prophet->prophesize();
		$cache->willImplement(ActivityCache::class);
		$cache->getActivity('TestUnit1', [])
			->shouldBeCalledTimes(1)
			->willReturn((object)[
				"actions"=>[
					"start"=>TestUnit1\Activity1::class."::action1",
					TestUnit1\Activity1::class."::action1"=>TestUnit1\Activity1::class."::action2",
					TestUnit1\Activity1::class."::action2"=>(object)[
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
					TestUnit1\Activity1::class."::action9"=>(object)[
						"+"=>TestUnit1\Activity1::class."::action8",
						"-"=>TestUnit1\Activity1::class."::action10",
					],
					TestUnit1\Activity1::class."::action10"=>[
						TestUnit1\Activity1::class."::action11",
						TestUnit1\Activity1::class."::action13",
						TestUnit1\Activity1::class."::action15",
					],
					TestUnit1\Activity1::class."::action11"=>TestUnit1\Activity1::class."::action12",
					TestUnit1\Activity1::class."::action12"=>0,
					TestUnit1\Activity1::class."::action13"=>TestUnit1\Activity1::class."::action14",
					TestUnit1\Activity1::class."::action14"=>0,
					TestUnit1\Activity1::class."::action15"=>TestUnit1\Activity1::class."::action16",
					TestUnit1\Activity1::class."::action16"=>0,
					0=>TestUnit1\Activity1::class."::action17",
					TestUnit1\Activity1::class."::action17"=>TestUnit1\Activity1::class."::action18",
					TestUnit1\Activity1::class."::action18"=>[
						"branch1"=>TestUnit1\Activity1::class."::action19",
						"branch2"=>TestUnit1\Activity1::class."::action21",
						"branch3"=>TestUnit1\Activity1::class."::action23",
					],
					TestUnit1\Activity1::class."::action19"=>TestUnit1\Activity1::class."::action20",
					TestUnit1\Activity1::class."::action20"=>1,
					TestUnit1\Activity1::class."::action21"=>TestUnit1\Activity1::class."::action22",
					TestUnit1\Activity1::class."::action22"=>1,
					TestUnit1\Activity1::class."::action23"=>TestUnit1\Activity1::class."::action24",
					TestUnit1\Activity1::class."::action24"=>1,
					1=>TestUnit1\Activity1::class."::action25",
					TestUnit1\Activity1::class."::action25"=>false,
				]
			]);

		$state = $prophet->prophesize();
		$state->willExtend(\stdClass::class);

		$errorMessage = null;

		$mainBranch = $prophet->prophesize();
		$mainBranch->willImplement(JournalBranch::class);
		$mainBranch->getState()->willReturn($state->reveal());
		$mainBranch->getErrorMessage()->willReturn(null);
		$mainBranch->setErrorMessage(Argument::type('string'))
			->will(function($args, $self)use($mainBranch,&$errorMessage) {
				[$errorMessage] = $args;
				$mainBranch->getErrorMessage()->willReturn($errorMessage);
				return $self;
			});
		$mainBranch->getRunning()->willReturn(false);
		$mainBranch->setRunning(Argument::type('bool'))
			->will(function($args, $self)use($mainBranch) {
				[$running] = $args;
				$mainBranch->getRunning()->willReturn($running);
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
			TestUnit1\Activity1::class."::action8",
			TestUnit1\Activity1::class."::action9",
			TestUnit1\Activity1::class."::action8",
			TestUnit1\Activity1::class."::action9",
			TestUnit1\Activity1::class."::action8",
			TestUnit1\Activity1::class."::action9",
			TestUnit1\Activity1::class."::action10",
			TestUnit1\Activity1::class."::action17", // join
			TestUnit1\Activity1::class."::action17", // join
			TestUnit1\Activity1::class."::action17", // join
			TestUnit1\Activity1::class."::action18",
			"split",
			TestUnit1\Activity1::class."::action21",
			TestUnit1\Activity1::class."::action22",
			TestUnit1\Activity1::class."::action25",
			"stop",
		];
		$actionCursor = 0;
		$mainBranch->getCurrentAction()->willReturn(null);
		$mainBranch->setCurrentAction(Argument::type("string"))
			->shouldBeCalledTimes(count($actions))
			->will(function($args, $self)use($mainBranch,$actions,&$actionCursor,&$errorMessage) {
				[$action] = $args;
				$nextAction = $actions[$actionCursor++];
				if ($action !== $nextAction) {
					if ($action === "exception") {
						throw new \Exception($errorMessage);
					}
					throw new \Exception("expected action $nextAction got action $action");
				}
				$mainBranch->getCurrentAction()->willReturn($action);
				return $self;
			});

		$journal = $prophet->prophesize();
		$journal->willImplement(Journal::class);
		$journal->getUnit()->willReturn(null);
		$journal->getDimensions()->willReturn(null);
		$journal->getMainBranch()->willReturn($mainBranch->reveal());
		$journal->getSplit()->willReturn(null);
		$journal->setSplit(Argument::type('array'))
			->will(function($args, $self)use($journal) {
				[$split] = $args;
				$journal->getSplit()->willReturn($split);
				return $self;
			});
		$journal->getFollowBranch()->willReturn(null);
		$journal->setFollowBranch(Argument::type('string'))
			->shouldBeCalledTimes(1)
			->will(function($args, $self)use($journal) {
				[$branch] = $args;
				$journal->getFollowBranch()->willReturn($branch);
				return $self;
			});
		$journal->setFollowBranch(null)
			->shouldBeCalledTimes(1)
			->will(function($args, $self)use($journal) {
				[$branch] = $args;
				$journal->getFollowBranch()->willReturn($branch);
				return $self;
			});

		$loop = 0;
		$journal->getInstance(TestUnit1\Activity1::class)
			->shouldBeCalledTimes(1)
			->will(function(array $args)use($prophet,&$loop){
				[$class] = $args;
				$instance = $prophet->prophesize();
				$instance->willExtend($class);
				$instance->action1(Argument::type(Activity::class))->willReturn(null);
				$instance->action2(Argument::type(Activity::class))->will(function(array $args){
					[$activity] = $args;
					$activity->decide(2);
				});
				$instance->action4(Argument::type(Activity::class))->willReturn(null);
				$instance->action5(Argument::type(Activity::class))->willReturn(null);
				$instance->action7(Argument::type(Activity::class))->willReturn(null);
				$instance->action8(Argument::type(Activity::class))->willReturn(null);
				$instance->action9(Argument::type(Activity::class))->will(function(array $args)use(&$loop){
					[$activity] = $args;
					$activity->decide($loop++ < 3);
				});
				$instance->action10(Argument::type(Activity::class))->willReturn(null);
				$instance->action11(Argument::type(Activity::class))->willReturn(null);
				$instance->action12(Argument::type(Activity::class))->willReturn(null);
				$instance->action13(Argument::type(Activity::class))->willReturn(null);
				$instance->action14(Argument::type(Activity::class))->willReturn(null);
				$instance->action15(Argument::type(Activity::class))->willReturn(null);
				$instance->action16(Argument::type(Activity::class))->willReturn(null);
				$instance->action17(Argument::type(Activity::class))->willReturn(null);
				$instance->action18(Argument::type(Activity::class))->willReturn(null);
				$instance->action19(Argument::type(Activity::class))->willReturn(null);
				$instance->action20(Argument::type(Activity::class))->willReturn(null);
				$instance->action21(Argument::type(Activity::class))->willReturn(null);
				$instance->action22(Argument::type(Activity::class))->willReturn(null);
				$instance->action25(Argument::type(Activity::class))->willReturn(null);
				return $instance->reveal();
			});

		$branches = [];
		$actionCursors = [0,0,0];
		$branchCursor = 0;
		$journal->getConcurrentBranches()->willReturn(null);
		$journal->fork()
			->shouldBeCalled(1)
			->will(function()use($prophet,$journal,&$branches,&$actionCursors,&$branchCursor){
				$branch = $prophet->prophesize();
				$branch->willImplement(JournalBranch::class);
				$branch->getState()->willReturn(new \stdClass);
				$branch->getErrorMessage()->willReturn(null);
				$branch->setErrorMessage(Argument::type('string'))
					->will(function($args, $self)use($branch,&$errorMessage) {
						[$errorMessage] = $args;
						$branch->getErrorMessage()->willReturn($errorMessage);
						return $self;
					});
				$branch->getRunning()->willReturn(true);
				$branch->setRunning(Argument::type('bool'))
					->will(function($args, $self)use($branch) {
						[$running] = $args;
						$branch->getRunning()->willReturn($running);
						return $self;
					});

				$allactions = [
					[
						TestUnit1\Activity1::class."::action11",
						TestUnit1\Activity1::class."::action12",
						"join",
					],
					[
						TestUnit1\Activity1::class."::action13",
						TestUnit1\Activity1::class."::action14",
						"join",
					],
					[
						TestUnit1\Activity1::class."::action15",
						TestUnit1\Activity1::class."::action16",
						"join",
					],
				];
				$actionCursor = &$actionCursors[$branchCursor];
				$actions = $allactions[$branchCursor++];
				$branch->getCurrentAction()->willReturn(null);
				$branch->setCurrentAction(Argument::type("string"))
					->shouldBeCalledTimes(count($actions))
					->will(function($args, $self)use($branch,$actions,&$actionCursor,&$errorMessage) {
						[$action] = $args;
						$nextAction = $actions[$actionCursor++];
						if ($action !== $nextAction) {
							if ($action === "exception") {
								throw new \Exception($errorMessage);
							}
							throw new \Exception("expected action $nextAction got action $action");
						}
						$branch->getCurrentAction()->willReturn($action);
						return $self;
					});

				$branches[] = $branch->reveal();
				$journal->getConcurrentBranches()->willReturn($branches);
				return $branch;
			});

		$journal->join()
			->shouldBeCalled(1)
			->will(function()use($journal){
				$journal->getConcurrentBranches()->willReturn(null);
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


		$activity = new Activity(
			$cache->reveal(),
			$journalRepository->reveal());

		$activity->load('TestUnit1', []);
		$activity->createJournal();
		$actions = $activity->actions();
		foreach ($actions as $action) {
			try {
				$action($activity);
			} catch (Throwable $e) {
				$actions->throw($e);
			}
		}
		$activity->followBranch("branch2");
		$actions = $activity->actions();
		foreach ($actions as $action) {
			try {
				$action($activity);
			} catch (Throwable $e) {
				$actions->throw($e);
			}
		}
		$prophet->checkPredictions();
		$this->assertEquals('TestUnit1', $activity->getUnit());
		$this->assertEquals([], $activity->getDimensions());
		$this->assertFalse($activity->isRunning());
		$this->assertNull($activity->getErrorMessage());
		$this->assertEquals("stop", $activity->getCurrentAction());
	}
}
