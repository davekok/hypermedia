<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Activity,
	Cache,
	Journal,
	JournalBranch,
	JournalRepository
};
use Sturdy\Activity\Meta\CacheItem_Activity;
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
		$cache->willImplement(Cache::class);
		$cache->getActivity('TestUnit1', TestUnit1\Activity1::class, [])
			->shouldBeCalledTimes(1)
			->willReturn((new CacheItem_Activity())
				->setClass(TestUnit1\Activity1::class)
				->setTags([])
				->setAction("start", "action1")
				->setAction("action1", "action2")
				->setAction("action2", (object)[1=>"action3", 2=>"action4", 3=>"action6"])
				->setAction("action3", "action7")
				->setAction("action4", "action5")
				->setAction("action5", "action7")
				->setAction("action6", "action7")
				->setAction("action7", "action8")
				->setAction("action8", "action9")
				->setAction("action8", "action9")
				->setAction("action9", (object)["+"=>"action8","-"=>"action10"])
				->setAction("action10", ["action11","action13","action15",])
				->setAction("action11", "action12")
				->setAction("action12", 0)
				->setAction("action13", "action14")
				->setAction("action14", 0)
				->setAction("action15", "action16")
				->setAction("action16", 0)
				->setAction(0, "action17")
				->setAction("action17", "action18")
				->setAction("action18", ["branch1"=>"action19","branch2"=>"action21","branch3"=>"action23"])
				->setAction("action19", "action20")
				->setAction("action20", 1)
				->setAction("action21", "action22")
				->setAction("action22", 1)
				->setAction("action23", "action24")
				->setAction("action24", 1)
				->setAction(1, "action25")
				->setAction("action25", false)
			);

		$errorMessage = null;

		$mainBranch = $prophet->prophesize();
		$mainBranch->willImplement(JournalBranch::class);
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
			"action1",
			"action2",
			"action4",
			"action5",
			"action7",
			"action8",
			"action9",
			"action8",
			"action9",
			"action8",
			"action9",
			"action8",
			"action9",
			"action10",
			"action17", // join
			"action17", // join
			"action17", // join
			"action18",
			"split",
			"action21",
			"action22",
			"action25",
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

		$loop = 0;
		$object = $prophet->prophesize();
		$object->willExtend(TestUnit1\Activity1::class);
		$object->action1(Argument::type(Activity::class))->willReturn(null);
		$object->action2(Argument::type(Activity::class))->will(function(array $args){
			[$activity] = $args;
			$activity->decide(2);
		});
		$object->action4(Argument::type(Activity::class))->willReturn(null);
		$object->action5(Argument::type(Activity::class))->willReturn(null);
		$object->action7(Argument::type(Activity::class))->willReturn(null);
		$object->action8(Argument::type(Activity::class))->willReturn(null);
		$object->action9(Argument::type(Activity::class))->will(function(array $args)use(&$loop){
			[$activity] = $args;
			$activity->decide($loop++ < 3);
		});
		$object->action10(Argument::type(Activity::class))->willReturn(null);
		$object->action11(Argument::type(Activity::class))->willReturn(null);
		$object->action12(Argument::type(Activity::class))->willReturn(null);
		$object->action13(Argument::type(Activity::class))->willReturn(null);
		$object->action14(Argument::type(Activity::class))->willReturn(null);
		$object->action15(Argument::type(Activity::class))->willReturn(null);
		$object->action16(Argument::type(Activity::class))->willReturn(null);
		$object->action17(Argument::type(Activity::class))->willReturn(null);
		$object->action18(Argument::type(Activity::class))->willReturn(null);
		$object->action19(Argument::type(Activity::class))->willReturn(null);
		$object->action20(Argument::type(Activity::class))->willReturn(null);
		$object->action21(Argument::type(Activity::class))->willReturn(null);
		$object->action22(Argument::type(Activity::class))->willReturn(null);
		$object->action25(Argument::type(Activity::class))->willReturn(null);
		$object = $object->reveal();

		$mainBranch->getCurrentObject()->willReturn($object);
		$mainBranch->setCurrentObject(Argument::type(TestUnit1\Activity1::class))->will(function($args, $self){return $self;});

		$journal = $prophet->prophesize();
		$journal->willImplement(Journal::class);
		$journal->getSourceUnit()->willReturn(null);
		$journal->getType()->willReturn(null);
		$journal->getClass()->willReturn(null);
		$journal->getTags()->willReturn(null);
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

		$branches = [];
		$actionCursors = [0,0,0];
		$branchCursor = 0;
		$journal->getConcurrentBranches()->willReturn(null);
		$journal->fork()
			->shouldBeCalled(1)
			->will(function()use($prophet,$journal,&$branches,&$actionCursors,&$branchCursor,$object){
				$branch = $prophet->prophesize();
				$branch->willImplement(JournalBranch::class);
				$branch->getCurrentObject()->willReturn($object);
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
						"action11",
						"action12",
						"join",
					],
					[
						"action13",
						"action14",
						"join",
					],
					[
						"action15",
						"action16",
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
		$journalRepository->createJournal("TestUnit1", Journal::activity, TestUnit1\Activity1::class, [])
			->shouldBeCalledTimes(1)
			->will(function($args)use($journal){
				[$sourceUnit, $type, $class, $tags] = $args;
				$journal->getSourceUnit()->willReturn($sourceUnit);
				$journal->getType()->willReturn($type);
				$journal->getClass()->willReturn($class);
				$journal->getTags()->willReturn($tags);
				return $journal->reveal();
			});
		$journalRepository->saveJournal(Argument::type(Journal::class))
			->shouldBeCalled();


		$activity = new Activity(
			$cache->reveal(),
			$journalRepository->reveal(),
			"TestUnit1");

		$activity->load(TestUnit1\Activity1::class, []);
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
		$this->assertEquals('TestUnit1', $activity->getSourceUnit());
		$this->assertEquals(TestUnit1\Activity1::class, $activity->getClass());
		$this->assertEquals([], $activity->getTags());
		$this->assertFalse($activity->isRunning());
		$this->assertNull($activity->getErrorMessage());
		$this->assertEquals("stop", $activity->getCurrentAction());
	}
}
