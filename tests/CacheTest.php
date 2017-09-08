<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\{
	Cache,
	CacheSourceUnit,
	CacheItem_Activity
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
		$unit->getName()->willReturn("testunit");
		$unit->getTagOrder()->willReturn(["dim1", "dim2", "dim3"]);
		$unit->getWildCardTags()->willReturn([]);
		$cacheItem = new CacheItem_Activity;
		$cacheItem->setClass("Foo");
		$cacheItem->setTags(["dim1"=>1, "dim2"=>2, "dim3"=>3]);
		$cacheItem->setActions($expectedActions);
		$unit->getCacheItems()->willReturn([$cacheItem]);

		$cachepool = new ArrayCachePool;
		$cache = new Cache($cachepool);
		$cache->updateSourceUnit($unit->reveal());

		$order = $cachepool->getItem(hash("sha256","/sturdy-activity/testunit"));
		$this->assertTrue($order->isHit(), "tags order is not stored");
		$this->assertEquals([["dim1", "dim2", "dim3"],[]], unserialize($order->get()));

		$cachedItem = $cachepool->getItem(hash("sha256","/sturdy-activity/testunit/Activity/Foo/".serialize(["dim1"=>1, "dim2"=>2, "dim3"=>3])));
		$this->assertTrue($cachedItem->isHit(), "cache item has not been stored");
		$this->assertEquals($cacheItem, unserialize($cachedItem->get()));

		$activity = $cache->getActivity("testunit", "Foo", ["dim1"=>1, "dim2"=>2, "dim3"=>3]);
		$this->assertTrue(is_object($activity));
		$this->assertEquals($expectedActions, $activity->getActions());
	}

	public function testWildCardTags()
	{
		$prophet = new Prophet;

		$unit = $prophet->prophesize();
		$unit->willImplement(CacheSourceUnit::class);
		$unit->getName()->willReturn("testunit");
		$unit->getTagOrder()->willReturn(["dim1","dim2"]);
		$unit->getWildCardTags()->willReturn(["dim2"]);
		$cacheItem = new CacheItem_Activity;
		$cacheItem->setClass("Foo");
		$cacheItem->setTags(["dim1"=>"1","dim2"=>true]);
		$cacheItem->setActions(["action"=>false]);
		$unit->getCacheItems()->willReturn([$cacheItem]);

		$cachepool = new ArrayCachePool;
		$cache = new Cache($cachepool);
		$cache->updateSourceUnit($unit->reveal());

		$activity = $cache->getActivity("testunit", "Foo", ["dim1"=>"1"]);
		$this->assertNull($activity);

		$activity = $cache->getActivity("testunit", "Foo", ["dim1"=>"1", "dim2"=>true]);
		$this->assertTrue(is_object($activity));

		$activity = $cache->getActivity("testunit", "Foo", ["dim1"=>"1", "dim2"=>"2"]);
		$this->assertTrue(is_object($activity));
	}
}
