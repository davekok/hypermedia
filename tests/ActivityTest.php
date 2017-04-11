<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{Facade,Diagram,Entity,Repository,StateFactoryInterface};
use PHPUnit\Framework\TestCase;

class ActivityTest extends TestCase
{
	public function testUpdateUnit()
	{
		$activityRepository = $this->getMockBuilder(Repository\ActivityRepositoryInterface::class)->getMock();
		$journalRepository = $this->getMockBuilder(Repository\JournalRepositoryInterface::class)->getMock();
		$stateFactory = $this->getMockBuilder(StateFactoryInterface::class)->getMock();

		$facade = new Facade($activityRepository, $journalRepository, $stateFactory);

		$cacheDir = $facade->getCacheDir();
		$this->assertTrue(is_dir($cacheDir));

		$cache = $facade->getCache(new \Doctrine\Common\Annotations\AnnotationReader());
		$unit = $cache->updateUnit('TestUnit1', __DIR__.'/TestUnit1/');
		$this->assertTrue(is_dir("$cacheDir/TestUnit1"));
		$this->assertTrue(is_file("$cacheDir/TestUnit1.object"));
		$this->assertEquals($unit, unserialize(file_get_contents("$cacheDir/TestUnit1.object")));
	}

	public function testCacheActivity()
	{
		$activityRepository = $this->getMockBuilder(Repository\ActivityRepositoryInterface::class)->getMock();
		$journalRepository = $this->getMockBuilder(Repository\JournalRepositoryInterface::class)->getMock();
		$stateFactory = $this->getMockBuilder(StateFactoryInterface::class)->getMock();

		$facade = new Facade($activityRepository, $journalRepository, $stateFactory);
		$cacheDir = $facade->getCacheDir();
		$cache = $facade->getCache(new \Doctrine\Common\Annotations\AnnotationReader());
		$cache->cacheActivities($cache->getUnit('TestUnit1'));
		$this->assertTrue(is_file("$cacheDir/TestUnit1/activity.php"));
		$this->assertTrue(is_readable("$cacheDir/TestUnit1/activity.php"));
		$activity = include("$cacheDir/TestUnit1/activity.php");
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
		$activityRepository = $this->getMockBuilder(Repository\ActivityRepositoryInterface::class)->getMock();
		$journalRepository = $this->getMockBuilder(Repository\JournalRepositoryInterface::class)->getMock();
		$stateFactory = $this->getMockBuilder(StateFactoryInterface::class)->getMock();

		$facade = new Facade($activityRepository, $journalRepository, $stateFactory);
		$cache = $facade->getCache(new \Doctrine\Common\Annotations\AnnotationReader());
		$unit = $cache->getUnit('TestUnit1');
		$diagram = new Diagram($unit);
		$docDir = sys_get_temp_dir()."/doc/";
		$diagram->write($docDir);
		$this->assertTrue(is_dir($docDir));
	}
}
