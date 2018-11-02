<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Cache;
use Sturdy\Activity\Meta\{
	CacheItem_Activity, Get, Post, SourceUnit, TagMatcher, Resource
};
use PHPUnit\Framework\TestCase;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Prophecy\{
	Argument,
	Prophet
};
use stdClass;
use Faker;

class SourceUnitTest extends TestCase
{
	private $unit;
	private $faker;
	
	private $resources;
	
	public function __construct()
	{
		parent::__construct();
		$this->faker = Faker\Factory::create();
	}
	
	public function setUp()
	{
		$a = (new Resource($this->faker->word, $this->faker->sentence()))->addVerb((new Get())->setMethod("foo"));
		$b = (new Resource($this->faker->word, $this->faker->sentence()))->addVerb((new Post())->setMethod("bar"));
		$c = (new Resource($this->faker->word, $this->faker->sentence()))->addVerb((new Get())->setMethod("baz"));
		$d = (new Resource($this->faker->word, $this->faker->sentence()))->addVerb((new Post())->setMethod("quz"));
		
		$resources = [$a,$b,$c, $d];
		
		$this->unit = new SourceUnit("Foo");
		foreach ($resources as $resource) {
			$this->resources[] = $resource;
			$this->unit->addResource($resource);
		}
	}
	
	public function testSourceUnitResources()
	{
		$this->assertEquals($this->resources, $this->unit->getResources(),"Resources");
	}
	
	public function testSourceUnitResourceCacheItems()
	{
		$items = iterator_to_array($this->unit->getCacheItems());
		$this->assertEquals(4,count($items),"Resource cache item count");
	}
}
