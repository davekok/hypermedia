<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Unit;
use PHPUnit\Framework\TestCase;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Prophecy\{
	Argument,
	Prophet
};

class UnitTest extends TestCase
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
		$unit = new Unit("Foo");
		$unit->addAction("Home", "action1", true, "Home::action2", ["route"=>"/"]);
		$unit->addAction("Home", "action1u", true, "Home::action2", ["route"=>"/", "role"=>"user"]);
		$unit->addAction("Home", "action1a", true, "Home::action2", ["route"=>"/", "role"=>"admin"]);
		$unit->addAction("Home", "action2", false, "Home::action3", []);
		$unit->addAction("Home", "action3", false, null, []);
		$unit->addAction("Home", "action3", false, "Home::action4", ["role"=>"user"]);
		$unit->addAction("Home", "action3", false, "Home::action5", ["role"=>"admin"]);
		$unit->addAction("Home", "action3", false, "Home::action6", ["route"=>"/","role"=>"admin"]);
		$unit->addAction("Home", "action3", false, "Home::action7", ["route"=>"/"]);
		$unit->addAction("Home", "action3", false, "Home::action8", ["route"=>"/bar"]);
		$unit->addAction("Home", "action3", false, "Home::action9", ["role"=>"guest"]);
		$unit->addAction("Home", "action3", false, "Home::action10", ["route"=>"/","role"=>"guest"]);
		$unit->addAction("Home", "action3", false, "Home::action11", ["route"=>"/","role"=>"admin"]); //duplicate
		$unit->addAction("Home", "action4", false, null, []);
		$unit->addAction("Home", "action5", false, null, []);
		$unit->addAction("Home", "action6", false, null, []);
		$unit->addAction("Home", "action7", false, null, []);
		$unit->addAction("Home", "action8", false, null, []);
		$unit->addAction("Home", "action9", false, null, []);
		$unit->addAction("Home", "action10", false, null, []);
		$unit->addAction("Home", "action11", false, null, []);
		$this->assertEquals(["Home"], $unit->getClasses());
		$this->assertEquals(["route", "role"], $unit->getDimensions());
		$expectedActions = [
			"start" => [
				(object)["next"=>"Home::action1","dimensions"=>["route"=>"/"]],
				(object)["next"=>"Home::action1u","dimensions"=>["route"=>"/","role"=>"user"]],
				(object)["next"=>"Home::action1a","dimensions"=>["route"=>"/","role"=>"admin"]],
			],
			"Home::action1" => [
				(object)["next"=>"Home::action2","dimensions"=>["route"=>"/"]],
			],
			"Home::action1u" => [
				(object)["next"=>"Home::action2","dimensions"=>["route"=>"/","role"=>"user"]],
			],
			"Home::action1a" => [
				(object)["next"=>"Home::action2","dimensions"=>["route"=>"/","role"=>"admin"]],
			],
			"Home::action2" => [
				(object)["next"=>"Home::action3","dimensions"=>[]],
			],
			"Home::action3" => [
				0=>(object)["next"=>null,"dimensions"=>[]],
				1=>(object)["next"=>"Home::action4","dimensions"=>["role"=>"user"]],
				2=>(object)["next"=>"Home::action5","dimensions"=>["role"=>"admin"]],
				3=>(object)["next"=>"Home::action6","dimensions"=>["route"=>"/","role"=>"admin"]],
				4=>(object)["next"=>"Home::action7","dimensions"=>["route"=>"/"]],
				5=>(object)["next"=>"Home::action8","dimensions"=>["route"=>"/bar"]],
				6=>(object)["next"=>"Home::action9","dimensions"=>["role"=>"guest"]],
				7=>(object)["next"=>"Home::action10","dimensions"=>["route"=>"/","role"=>"guest"]],
				8=>(object)["next"=>"Home::action11","dimensions"=>["route"=>"/","role"=>"admin"]],
			],
			"Home::action4" => [(object)["next"=>null,"dimensions"=>[]]],
			"Home::action5" => [(object)["next"=>null,"dimensions"=>[]]],
			"Home::action6" => [(object)["next"=>null,"dimensions"=>[]]],
			"Home::action7" => [(object)["next"=>null,"dimensions"=>[]]],
			"Home::action8" => [(object)["next"=>null,"dimensions"=>[]]],
			"Home::action9" => [(object)["next"=>null,"dimensions"=>[]]],
			"Home::action10" => [(object)["next"=>null,"dimensions"=>[]]],
			"Home::action11" => [(object)["next"=>null,"dimensions"=>[]]],
		];
		$this->assertEquals($expectedActions, $unit->getActions(), "actions");
		$this->assertEquals($unit->findBestMatch("start", ["route"=>"/"], ["role"]), $expectedActions["start"][0], "best match 1");
		$this->assertEquals($unit->findBestMatch("start", ["route"=>"/","role"=>"user"], []), $expectedActions["start"][1], "best match 2");
		$this->assertEquals($unit->findBestMatch("start", ["route"=>"/","role"=>"admin"], []), $expectedActions["start"][2], "best match 3");
		$this->assertEquals($unit->findBestMatch("Home::action3", ["route"=>"/"], ["role"]), $expectedActions["Home::action3"][4], "best match 4");
		$this->assertEquals($unit->findBestMatch("Home::action3", ["route"=>"/foo"], ["role"]), $expectedActions["Home::action3"][0], "best match 5");
		$this->assertEquals($unit->findBestMatch("Home::action3", ["role"=>"user"], []), $expectedActions["Home::action3"][1], "best match 6");
		$this->assertEquals($unit->findBestMatch("Home::action3", ["route"=>"/","role"=>"user"], []), $expectedActions["Home::action3"][4], "best match 7");
		$this->assertEquals($unit->findBestMatch("Home::action3", ["role"=>"admin"], []), $expectedActions["Home::action3"][2], "best match 8");
		$this->assertEquals($unit->findBestMatch("Home::action3", ["route"=>"/","role"=>"admin"], []), $expectedActions["Home::action3"][3], "best match 9");

		$actions = [];
		$unit->walk($actions, "start", ["route"=>"/"], ["role"]);
		$this->assertEquals([
			"start" => "Home::action1",
			"Home::action1" => "Home::action2",
			"Home::action2" => "Home::action3",
			"Home::action3" => "Home::action7",
			"Home::action7" => null,
		], $actions);

		$actions = [];
		$unit->walk($actions, "start", ["route"=>"/","role"=>"admin"], []);
		$this->assertEquals([
			"start" => "Home::action1a",
			"Home::action1a" => "Home::action2",
			"Home::action2" => "Home::action3",
			"Home::action3" => "Home::action6",
			"Home::action6" => null,
		], $actions);

		$actions = [];
		$unit->walk($actions, "start", ["route"=>"/","role"=>"user"], []);
		$this->assertEquals([
			"start" => "Home::action1u",
			"Home::action1u" => "Home::action2",
			"Home::action2" => "Home::action3",
			"Home::action3" => "Home::action7",
			"Home::action7" => null,
		], $actions);

		$unit->compile();

		$this->assertEquals([
			(object)[
				"dimensions"=>["route"=>"/","role"=>null],
				"actions" => [
					"start" => "Home::action1",
					"Home::action1" => "Home::action2",
					"Home::action2" => "Home::action3",
					"Home::action3" => "Home::action7",
					"Home::action7" => null,
				],
			],
			(object)[
				"dimensions"=>["route"=>"/","role"=>"user"],
				"actions" => [
					"start" => "Home::action1u",
					"Home::action1u" => "Home::action2",
					"Home::action2" => "Home::action3",
					"Home::action3" => "Home::action7",
					"Home::action7" => null,
				],
			],
			(object)[
				"dimensions"=>["route"=>"/","role"=>"admin"],
				"actions" => [
					"start" => "Home::action1a",
					"Home::action1a" => "Home::action2",
					"Home::action2" => "Home::action3",
					"Home::action3" => "Home::action6",
					"Home::action6" => null,
				],
			],
			(object)[
				"dimensions"=>["route"=>"/","role"=>"guest"],
				"actions" => [
					"start" => "Home::action1",
					"Home::action1" => "Home::action2",
					"Home::action2" => "Home::action3",
					"Home::action3" => "Home::action10",
					"Home::action10" => null,
				],
			],
		], $unit->getActivities(), "activities");
	}
}
