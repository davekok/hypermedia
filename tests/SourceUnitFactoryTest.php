<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\SourceUnitFactory;
use Sturdy\Activity\Action;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Annotations\AnnotationReader;

class SourceUnitFactoryTest extends TestCase
{
	public function testCreateUnit1()
	{
		$class = TestUnit1\Activity1::class;
		$unit = (new SourceUnitFactory(new AnnotationReader))->createSourceUnit('TestUnit1', __DIR__.'/TestUnit1/');
		$this->assertEquals("TestUnit1", $unit->getName(), "unit name");
		$this->assertEquals([TestUnit1\Activity1::class], $unit->getClasses(), "classes");
		$this->assertEquals([], $unit->getDimensions(), "dimensions");
		$actions = $unit->getActions();
		foreach ($actions as &$subaction) {
			foreach ($subaction as &$action) {
				$action = (string)$action;
			}
		}
		$this->assertEquals([
			"start" => ["[start] >$class::action1"],
			"$class::action1" => ["[$class::action1] start >$class::action2"],
			"$class::action2" => ["[$class::action2] =1 >$class::action3 =2 >$class::action4 =3 >$class::action6"],
			"$class::action3" => ["[$class::action3] >$class::action7"],
			"$class::action4" => ["[$class::action4] >$class::action5"],
			"$class::action5" => ["[$class::action5] readonly >$class::action7"],
			"$class::action6" => ["[$class::action6] >$class::action7"],
			"$class::action7" => ["[$class::action7] >$class::action8"],
			"$class::action8" => ["[$class::action8] >$class::action9"],
			"$class::action9" => ["[$class::action9] =true >$class::action8 =false >$class::action10"],
			"$class::action10" => ["[$class::action10] end"],
		], $actions, "actions");
	}

	public function testCreateUnit2()
	{
		$unit = (new SourceUnitFactory(new AnnotationReader))->createSourceUnit('TestUnit2', __DIR__.'/TestUnit2/');
		$this->assertEquals($unit->getName(), "TestUnit2", "unit name");
		$classes = $unit->getClasses();
		$this->assertTrue(in_array(TestUnit2\Activity1::class, $classes));
		$this->assertTrue(in_array(TestUnit2\Activity2::class, $classes));
		$this->assertEquals(["route", "role"], $unit->getDimensions(), "dimensions");
		$activities = $unit->getActivities();
		$this->assertEquals(count($activities), 2);
		$this->assertTrue($activities[0]->readonly xor $activities[1]->readonly);
	}
}
