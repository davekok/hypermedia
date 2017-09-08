<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\{
	Action,
	Activity,
	CacheItem_Activity,
	SourceUnit,
	TagMatcher
};
use PHPUnit\Framework\TestCase;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Prophecy\{
	Argument,
	Prophet
};
use stdClass;

class SourceUnitTest extends TestCase
{
	private $unit;

	public function setUp()
	{
		$actions = [
			"[action1] start >action2 #route=/",
			"[action1u] start >action2 #route=/ #role=user",
			"[action1a] start >action2 #route=/ #role=admin",
			"[action2] >action3 #route=/",
			"[action3] end",
			"[action3] >action4 #role=user",
			"[action3] >action5 #role=admin",
			"[action3] >action6 #route=/ #role=admin",
			"[action3] >action7 #route=/",
			"[action3] >action8 #route=/bar",
			"[action3] >action9 #role=guest",
			"[action3] >action10 #route=/ #role=guest",
			"[action3] >action11 #route=/ #role=admin", // duplicate
			"[action4] end",
			"[action5] end",
			"[action6] end",
			"[action7] end",
			"[action8] end",
			"[action9] end",
			"[action10] end",
			"[action11] end",
		];
		$this->unit = new SourceUnit("Foo");
		$activity = new Activity("Home", "");
		foreach ($actions as $action) {
			$activity->addAction(Action::createFromText($action));
		}
		$this->unit->addActivity($activity);
	}

	public function testClasses()
	{
		$this->assertEquals(["Home"], $this->unit->getClasses());
	}

	public function testTagOrder()
	{
		$this->assertEquals(["route", "role"], $this->unit->getTagOrder());
	}

	public function testAllActionsPresent()
	{
		[$activity] = $this->unit->getActivities();
		$this->assertTrue(!empty($activity->getActionsWithName("start")));
		$this->assertTrue(!empty($activity->getActionsWithName("action1")));
		$this->assertTrue(!empty($activity->getActionsWithName("action1u")));
		$this->assertTrue(!empty($activity->getActionsWithName("action1a")));
		$this->assertTrue(!empty($activity->getActionsWithName("action2")));
		$this->assertTrue(!empty($activity->getActionsWithName("action3")));
		$this->assertTrue(!empty($activity->getActionsWithName("action4")));
		$this->assertTrue(!empty($activity->getActionsWithName("action5")));
		$this->assertTrue(!empty($activity->getActionsWithName("action6")));
		$this->assertTrue(!empty($activity->getActionsWithName("action7")));
		$this->assertTrue(!empty($activity->getActionsWithName("action8")));
		$this->assertTrue(!empty($activity->getActionsWithName("action9")));
		$this->assertTrue(!empty($activity->getActionsWithName("action10")));
		$this->assertTrue(!empty($activity->getActionsWithName("action11")));
	}

	public function testBestMatch()
	{
		[$activity] = $this->unit->getActivities();
		$m = new TagMatcher([], ["route", "role"]);
		$start = $activity->getActionsWithName("start");
		$action3 = $activity->getActionsWithName("action3");
		$this->assertEquals((string)$m->setTags(["route"=>"/"                ])->findBestMatch($start  ), "[start] > action1 #route=/",               "best match 1");
		$this->assertEquals((string)$m->setTags(["route"=>"/","role"=>"user" ])->findBestMatch($start  ), "[start] > action1u #route=/ #role=user",   "best match 2");
		$this->assertEquals((string)$m->setTags(["route"=>"/","role"=>"admin"])->findBestMatch($start  ), "[start] > action1a #route=/ #role=admin",  "best match 3");
		$this->assertEquals((string)$m->setTags(["route"=>"/foo"             ])->findBestMatch($action3), "[action3] end",                            "best match 4");
		$this->assertEquals((string)$m->setTags([             "role"=>"user" ])->findBestMatch($action3), "[action3] > action4 #role=user",           "best match 5");
		$this->assertEquals((string)$m->setTags(["route"=>"/","role"=>"user" ])->findBestMatch($action3), "[action3] > action7 #route=/",             "best match 6");
		$this->assertEquals((string)$m->setTags(["route"=>"/"                ])->findBestMatch($action3), "[action3] > action7 #route=/",             "best match 7");
		$this->assertEquals((string)$m->setTags([             "role"=>"admin"])->findBestMatch($action3), "[action3] > action5 #role=admin",          "best match 8");
		$this->assertEquals((string)$m->setTags(["route"=>"/","role"=>"admin"])->findBestMatch($action3), "[action3] > action6 #route=/ #role=admin", "best match 9");
	}

	public function testCompile()
	{
		$items = iterator_to_array($this->unit->getCacheItems());
		$this->assertEquals(4, count($items));
		$a1 = new CacheItem_Activity();
		$a1->setClass("Home");
		$a1->setTags(["route"=>"/","role"=>null]);
		$a1->setAction("start", "action1");
		$a1->setAction("action1", "action2");
		$a1->setAction("action2", "action3");
		$a1->setAction("action3", "action7");
		$a1->setAction("action7", false);
		$a2 = new CacheItem_Activity();
		$a2->setClass("Home");
		$a2->setTags(["route"=>"/","role"=>"user"]);
		$a2->setAction("start", "action1u");
		$a2->setAction("action1u", "action2");
		$a2->setAction("action2", "action3");
		$a2->setAction("action3", "action7");
		$a2->setAction("action7", false);
		$a3 = new CacheItem_Activity();
		$a3->setClass("Home");
		$a3->setTags(["route"=>"/","role"=>"admin"]);
		$a3->setAction("start", "action1a");
		$a3->setAction("action1a", "action2");
		$a3->setAction("action2", "action3");
		$a3->setAction("action3", "action6");
		$a3->setAction("action6", false);
		$a4 = new CacheItem_Activity();
		$a4->setClass("Home");
		$a4->setTags(["route"=>"/","role"=>"guest"]);
		$a4->setAction("start", "action1");
		$a4->setAction("action1", "action2");
		$a4->setAction("action2", "action3");
		$a4->setAction("action3", "action10");
		$a4->setAction("action10", false);
		$this->assertEquals([$a1, $a2, $a3, $a4], $items, "items");
	}
}
