<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Action,
	SourceUnit
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
			"[Home::action1] start >action2 #route=/",
			"[Home::action1u] start >action2 #route=/ #role=user",
			"[Home::action1a] start >action2 #route=/ #role=admin",
			"[Home::action2] >action3 #route=/",
			"[Home::action3] end",
			"[Home::action3] >action4 #role=user",
			"[Home::action3] >action5 #role=admin",
			"[Home::action3] >action6 #route=/ #role=admin",
			"[Home::action3] >action7 #route=/",
			"[Home::action3] >action8 #route=/bar",
			"[Home::action3] >action9 #role=guest",
			"[Home::action3] >action10 #route=/ #role=guest",
			"[Home::action3] >action11 #route=/ #role=admin", // duplicate
			"[Home::action4] end",
			"[Home::action5] end",
			"[Home::action6] end",
			"[Home::action7] end",
			"[Home::action8] end",
			"[Home::action9] end",
			"[Home::action10] end",
			"[Home::action11] end",
		];
		$this->unit = new SourceUnit("Foo");
		foreach ($actions as $action) {
			$this->unit->addAction(Action::createFromText($action));
		}
	}

	public function testClasses()
	{
		$this->assertEquals(["Home"], $this->unit->getClasses());
	}

	public function testDimensions()
	{
		$this->assertEquals(["route", "role"], $this->unit->getDimensions());
	}

	public function testAllActionsPresent()
	{
		$unitActions = $this->unit->getActions();
		$this->assertTrue(isset($unitActions["start"]));
		$this->assertTrue(isset($unitActions["Home::action1"]));
		$this->assertTrue(isset($unitActions["Home::action1u"]));
		$this->assertTrue(isset($unitActions["Home::action1a"]));
		$this->assertTrue(isset($unitActions["Home::action2"]));
		$this->assertTrue(isset($unitActions["Home::action3"]));
		$this->assertTrue(isset($unitActions["Home::action4"]));
		$this->assertTrue(isset($unitActions["Home::action5"]));
		$this->assertTrue(isset($unitActions["Home::action6"]));
		$this->assertTrue(isset($unitActions["Home::action7"]));
		$this->assertTrue(isset($unitActions["Home::action8"]));
		$this->assertTrue(isset($unitActions["Home::action9"]));
		$this->assertTrue(isset($unitActions["Home::action10"]));
		$this->assertTrue(isset($unitActions["Home::action11"]));
	}

	public function testBestMatch()
	{
		$this->assertEquals("[start] >Home::action1 #route=/", (string)$this->unit->findBestMatch("start", ["route"=>"/"], ["role"]), "best match 1");
		$this->assertEquals("[start] >Home::action1u #route=/ #role=user", (string)$this->unit->findBestMatch("start", ["route"=>"/","role"=>"user"], []), "best match 2");
		$this->assertEquals("[start] >Home::action1a #route=/ #role=admin", (string)$this->unit->findBestMatch("start", ["route"=>"/","role"=>"admin"], []), "best match 3");
		$this->assertEquals("[Home::action3] end", (string)$this->unit->findBestMatch("Home::action3", ["route"=>"/foo"], ["role"]), "best match 4");
		$this->assertEquals("[Home::action3] >Home::action4 #role=user", (string)$this->unit->findBestMatch("Home::action3", ["role"=>"user"], ["route"]), "best match 5");
		$this->assertEquals("[Home::action3] >Home::action7 #route=/", (string)$this->unit->findBestMatch("Home::action3", ["route"=>"/","role"=>"user"], []), "best match 6");
		$this->assertEquals("[Home::action3] >Home::action7 #route=/", (string)$this->unit->findBestMatch("Home::action3", ["route"=>"/"], ["role"]), "best match 7");
		$this->assertEquals("[Home::action3] >Home::action5 #role=admin", (string)$this->unit->findBestMatch("Home::action3", ["role"=>"admin"], ["route"]), "best match 8");
		$this->assertEquals("[Home::action3] >Home::action6 #route=/ #role=admin", (string)$this->unit->findBestMatch("Home::action3", ["route"=>"/","role"=>"admin"], []), "best match 9");
	}

	public function testCompile()
	{
		$activities = $this->unit->compile();
		$this->assertEquals(4, count($activities));
		$this->assertEquals([
			(object)[
				"dimensions"=>["route"=>"/","role"=>null],
				"actions" => [
					"start" => "Home::action1",
					"Home::action1" => "Home::action2",
					"Home::action2" => "Home::action3",
					"Home::action3" => "Home::action7",
					"Home::action7" => false,
				],
				"readonly"=>false,
			],
			(object)[
				"dimensions"=>["route"=>"/","role"=>"user"],
				"actions" => [
					"start" => "Home::action1u",
					"Home::action1u" => "Home::action2",
					"Home::action2" => "Home::action3",
					"Home::action3" => "Home::action7",
					"Home::action7" => false,
				],
				"readonly"=>false,
			],
			(object)[
				"dimensions"=>["route"=>"/","role"=>"admin"],
				"actions" => [
					"start" => "Home::action1a",
					"Home::action1a" => "Home::action2",
					"Home::action2" => "Home::action3",
					"Home::action3" => "Home::action6",
					"Home::action6" => false,
				],
				"readonly"=>false,
			],
			(object)[
				"dimensions"=>["route"=>"/","role"=>"guest"],
				"actions" => [
					"start" => "Home::action1",
					"Home::action1" => "Home::action2",
					"Home::action2" => "Home::action3",
					"Home::action3" => "Home::action10",
					"Home::action10" => false,
				],
				"readonly"=>false,
			],
		], $activities, "activities");
	}
}
