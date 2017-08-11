<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Cache,
	CacheSourceUnit
};
use PHPUnit\Framework\TestCase;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Prophecy\{
	Argument,
	Prophet
};

class CacheTest extends TestCase
{
	public function testBasics()
	{
		$expectedActions = ["action1"=>"action2","action2"=>(object)["+"=>["action3","action4"],"-"=>false],"action3"=>false];

		$prophet = new Prophet;

		$unit = $prophet->prophesize();
		$unit->willImplement(CacheSourceUnit::class);
		$unit->isCompiled()->willReturn(true);
		$unit->getName()->willReturn('testunit');
		$unit->getDimensions()->willReturn(["dim1", "dim2", "dim3"]);
		$unit->getWildCardDimensions()->willReturn([]);
		$unit->getActivities()->willReturn([(object)["dimensions"=>["dim1"=>1, "dim2"=>2, "dim3"=>3],"actions"=>$expectedActions]]);

		$cachepool = new ArrayCachePool;
		$cache = new Cache($cachepool);
		$cache->updateUnit($unit->reveal());

		$order = $cachepool->getItem("sturdy-activity|testunit.dimensions");
		$this->assertTrue($order->isHit(), "dimensions order is not stored");
		$this->assertEquals([["dim1", "dim2", "dim3"],[]], unserialize($order->get()));

		$actions = $cachepool->getItem("sturdy-activity|testunit|".hash("sha256",serialize(["dim1"=>1, "dim2"=>2, "dim3"=>3])));
		$this->assertTrue($actions->isHit(), "actions are not stored");
		$this->assertEquals((object)["actions"=>$expectedActions], unserialize($actions->get()));

		$activity = $cache->getActivity("testunit", ["dim1"=>1, "dim2"=>2, "dim3"=>3]);
		$this->assertTrue(is_object($activity));
		$this->assertEquals($expectedActions, $activity->actions);
	}

	public function testWildCardDimensions()
	{
		$prophet = new Prophet;

		$unit = $prophet->prophesize();
		$unit->willImplement(CacheSourceUnit::class);
		$unit->getName()->willReturn('testunit');
		$unit->getDimensions()->willReturn(["dim1","dim2"]);
		$unit->getWildCardDimensions()->willReturn(["dim2"]);
		$unit->getActivities()->willReturn([(object)["dimensions"=>["dim1"=>"1", "dim2"=>true],"actions"=>["action"=>false]]]);
		$unit->isCompiled()->willReturn(true);

		$cachepool = new ArrayCachePool;
		$cache = new Cache($cachepool);
		$cache->updateUnit($unit->reveal());

		$activity = $cache->getActivity("testunit", ["dim1"=>"1"]);
		$this->assertNull($activity);

		$activity = $cache->getActivity("testunit", ["dim1"=>"1", "dim2"=>true]);
		$this->assertTrue(is_object($activity));

		$activity = $cache->getActivity("testunit", ["dim1"=>"1", "dim2"=>"2"]);
		$this->assertTrue(is_object($activity));
	}
}
