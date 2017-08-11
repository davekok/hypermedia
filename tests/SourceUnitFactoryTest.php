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
			"start" => ["[start] > $class::action1"],
			"$class::action1" => ["[$class::action1] start > $class::action2"],
			"$class::action2" => ["[$class::action2] 1> $class::action3 2> $class::action4 3> $class::action6"],
			"$class::action3" => ["[$class::action3] > $class::action7"],
			"$class::action4" => ["[$class::action4] > $class::action5"],
			"$class::action5" => ["[$class::action5] > $class::action7"],
			"$class::action6" => ["[$class::action6] > $class::action7"],
			"$class::action7" => ["[$class::action7] > $class::action8"],
			"$class::action8" => ["[$class::action8] > $class::action9"],
			"$class::action9" => ["[$class::action9] +> $class::action8 -> $class::action10"],
			"$class::action10" => ["[Tests\Sturdy\Activity\TestUnit1\Activity1::action10] > Tests\Sturdy\Activity\TestUnit1\Activity1::action11 | Tests\Sturdy\Activity\TestUnit1\Activity1::action13 | Tests\Sturdy\Activity\TestUnit1\Activity1::action15"],
			"$class::action11" => ["[$class::action11] > $class::action12"],
			"$class::action12" => ["[$class::action12] > $class::action17"],
			"$class::action13" => ["[$class::action13] > $class::action14"],
			"$class::action14" => ["[$class::action14] > $class::action17"],
			"$class::action15" => ["[$class::action15] > $class::action16"],
			"$class::action16" => ["[$class::action16] > $class::action17"],
			"$class::action17" => ["[$class::action17] >| > $class::action18"],
			"$class::action18" => ["[$class::action18] > branch1:Tests\Sturdy\Activity\TestUnit1\Activity1::action19 | branch2:Tests\Sturdy\Activity\TestUnit1\Activity1::action21 | branch3:Tests\Sturdy\Activity\TestUnit1\Activity1::action23"],
			"$class::action19" => ["[$class::action19] > $class::action20"],
			"$class::action20" => ["[$class::action20] > $class::action25"],
			"$class::action21" => ["[$class::action21] > $class::action22"],
			"$class::action22" => ["[$class::action22] > $class::action25"],
			"$class::action23" => ["[$class::action23] > $class::action24"],
			"$class::action24" => ["[$class::action24] > $class::action25"],
			"$class::action25" => ["[$class::action25] >| end"],
		], $actions, "actions");
	}

	public function testCreateUnit2()
	{
		$unit = (new SourceUnitFactory(new AnnotationReader))->createSourceUnit('TestUnit2', __DIR__.'/TestUnit2/');
		$unit->compile();
		$this->assertEquals("TestUnit2", $unit->getName(), "unit name");
		$classes = $unit->getClasses();
		$this->assertTrue(in_array(TestUnit2\Activity1::class, $classes), TestUnit2\Activity1::class." not in ".var_export($classes, true));
		$this->assertTrue(in_array(TestUnit2\Activity2::class, $classes), TestUnit2\Activity2::class." not in ".var_export($classes, true));
		$this->assertEquals(["route", "role"], $unit->getDimensions(), "dimensions");
		$activities = $unit->getActivities();
		$this->assertEquals(2, count($activities), "activity count");
	}
}
