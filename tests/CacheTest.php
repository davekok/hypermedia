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
	public function testCache()
	{
		$expectedActions = ["action1"=>"action2","action2"=>"action3","action3"=>null];

		$prophet = new Prophet;

		$unit = $prophet->prophesize();
		$unit->willImplement(CacheSourceUnit::class);
		$unit->getName()->willReturn('testunit');
		$unit->getDimensions()->willReturn(["dim1", "dim2", "dim3"]);
		$unit->getWildCardDimensions()->willReturn([]);
		$unit->getActivities()->willReturn([(object)["readonly"=>false,"dimensions"=>["dim1"=>1, "dim2"=>2, "dim3"=>3],"actions"=>$expectedActions]]);

		$cachepool = new ArrayCachePool;
		$cache = new Cache($cachepool);
		$cache->updateUnit($unit->reveal());

		$order = $cachepool->getItem("sturdy-activity|testunit.dimensions");
		$this->assertTrue($order->isHit(), "dimensions order is not stored");
		$this->assertEquals([["dim1", "dim2", "dim3"],[]], json_decode($order->get()));

		$actions = $cachepool->getItem("sturdy-activity|testunit|".hash("sha256",json_encode(["dim1"=>1, "dim2"=>2, "dim3"=>3])));
		$this->assertTrue($actions->isHit(), "actions are not stored");
		$this->assertEquals(["readonly"=>false, "actions"=>$expectedActions], json_decode($actions->get(), true));

		$activity = $cache->getActivity("testunit", ["dim1"=>1, "dim2"=>2, "dim3"=>3]);
		$this->assertTrue(is_array($activity));
		$this->assertEquals($activity["actions"], $expectedActions);
	}
}
