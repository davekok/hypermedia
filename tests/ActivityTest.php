<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{Activity,Cache,Diagrams,Entity,MkDir,Repository,StateFactory,InstanceFactory};
use PHPUnit\Framework\TestCase;

class ActivityTest extends TestCase
{
	use MkDir;

	private $cacheDir;

	public function __construct()
	{
		$this->cacheDir = $this->filterDir(null, 'cache');
	}

	public function testUpdateUnit()
	{
		$cache = new Cache(new \Doctrine\Common\Annotations\AnnotationReader());
		$unit = $cache->updateUnit('TestUnit1', __DIR__.'/TestUnit1/');
		$this->assertTrue(is_file($this->cacheDir.DIRECTORY_SEPARATOR."TestUnit1.object"));
		$this->assertEquals($unit, unserialize(file_get_contents($this->cacheDir.DIRECTORY_SEPARATOR."TestUnit1.object")));
	}

	public function testCacheActivity()
	{
		$cache = new Cache(new \Doctrine\Common\Annotations\AnnotationReader());
		$unit = $cache->getUnit('TestUnit1');
		$cache->cacheActivities($unit);
		$this->assertTrue(is_file($this->cacheDir."/TestUnit1/activity.php"));
		$this->assertTrue(is_readable($this->cacheDir."/TestUnit1/activity.php"));
		$activity = include($this->cacheDir."/TestUnit1/activity.php");
		$this->assertEquals($activity,
			array (
			  'start' => 'Tests\\Sturdy\\Activity\\Activity1::action1',
			  'Tests\\Sturdy\\Activity\\Activity1::action1' => 'Tests\\Sturdy\\Activity\\Activity1::action2',
			  'Tests\\Sturdy\\Activity\\Activity1::action2' =>
			  array (
			    1 => 'Tests\\Sturdy\\Activity\\Activity1::action3',
			    2 => 'Tests\\Sturdy\\Activity\\Activity1::action4',
			    3 => 'Tests\\Sturdy\\Activity\\Activity1::action5',
			  ),
			  'Tests\\Sturdy\\Activity\\Activity1::action3' => 'Tests\\Sturdy\\Activity\\Activity1::action6',
			  'Tests\\Sturdy\\Activity\\Activity1::action4' => 'Tests\\Sturdy\\Activity\\Activity1::action6',
			  'Tests\\Sturdy\\Activity\\Activity1::action5' => 'Tests\\Sturdy\\Activity\\Activity1::action6',
			  'Tests\\Sturdy\\Activity\\Activity1::action6' =>
			  array (
			    'true' => 'Tests\\Sturdy\\Activity\\Activity1::action7',
			    'false' => 'Tests\\Sturdy\\Activity\\Activity1::action9',
			  ),
			  'Tests\\Sturdy\\Activity\\Activity1::action7' => 'Tests\\Sturdy\\Activity\\Activity1::action8',
			  'Tests\\Sturdy\\Activity\\Activity1::action8' => 'Tests\\Sturdy\\Activity\\Activity1::action6',
			  'Tests\\Sturdy\\Activity\\Activity1::action9' => NULL,
			)
		);
	}

	public function testActivityDiagram()
	{
		$docDir = $this->filterDir(null, 'doc');

		$cache = new Cache(new \Doctrine\Common\Annotations\AnnotationReader());
		$unit = $cache->getUnit('TestUnit1');

		$diagrams = new Diagrams();
		$diagrams->write($unit);
		$this->assertTrue(is_dir($docDir));
	}

/*
		$nameRepository = $this->getMockBuilder(Repository\NameRepository::class)->getMock();
		$dimensionRepository = $this->getMockBuilder(Repository\DimensionRepository::class)->getMock();
		$journalRepository = $this->getMockBuilder(Repository\JournalRepository::class)->getMock();
		$stateFactory = $this->getMockBuilder(StateFactory::class)->getMock();
		$instanceFactory = $this->getMockBuilder(InstanceFactory::class)->getMock();
*/
}
