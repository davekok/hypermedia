<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\SourceUnitFactory;
use Sturdy\Activity\Meta\Action;
use Sturdy\Activity\Meta\Field;
use Sturdy\Activity\Meta\Verb;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Annotations\AnnotationReader;

class SourceUnitFactoryTest extends TestCase
{
	public function testCreateUnit1()
	{
		$unit = (new SourceUnitFactory(new AnnotationReader))->createSourceUnit('TestUnit1', __DIR__.'/TestUnit1/');
		$this->assertEquals("TestUnit1", $unit->getName(), "unit name");
		$this->assertEquals([TestUnit1\Activity1::class,TestUnit1\ResourceNoContent::class,], $unit->getClasses(), "classes");
		$this->assertEquals([], $unit->getTagOrder(), "tags");
		$actions = [];
		[$activity] = $unit->getActivities();
		foreach ($activity->getTaggables() as $action) {
			$actions[$action->getName()] = (string)$action;
		}
		$this->assertEquals([
			"start"    => "[start] > action1",
			"action1"  => "[action1] start > action2",
			"action2"  => "[action2] 1> action3 2> action4 3> action6",
			"action3"  => "[action3] > action7",
			"action4"  => "[action4] > action5",
			"action5"  => "[action5] > action7",
			"action6"  => "[action6] > action7",
			"action7"  => "[action7] > action8",
			"action8"  => "[action8] > action9",
			"action9"  => "[action9] +> action8 -> action10",
			"action10" => "[action10] > action11 | action13 | action15",
			"action11" => "[action11] > action12",
			"action12" => "[action12] > action17",
			"action13" => "[action13] > action14",
			"action14" => "[action14] > action17",
			"action15" => "[action15] > action16",
			"action16" => "[action16] > action17",
			"action17" => "[action17] >| > action18",
			"action18" => "[action18] > branch1:action19 | branch2:action21 | branch3:action23",
			"action19" => "[action19] > action20",
			"action20" => "[action20] > action25",
			"action21" => "[action21] > action22",
			"action22" => "[action22] > action25",
			"action23" => "[action23] > action24",
			"action24" => "[action24] > action25",
			"action25" => "[action25] >| end",
		], $actions, "actions");

		$taggables = [];
		[$resource] = $unit->getResources();
		foreach ($resource->getTaggables() as $taggable) {
			$taggables[$taggable->getName()] = (string)$taggable;
		}
		$this->assertEquals([
			"name" => "string required",
			"GET" => "",
			"POST" => "no-content",
		], $taggables, "taggables");
	}

	public function testCreateUnit2()
	{
		$unit = (new SourceUnitFactory(new AnnotationReader))->createSourceUnit('TestUnit2', __DIR__.'/TestUnit2/');
		$this->assertEquals("TestUnit2", $unit->getName(), "unit name");
		$classes = $unit->getClasses();
		$this->assertTrue(in_array(TestUnit2\Activity1::class, $classes), TestUnit2\Activity1::class." not in ".var_export($classes, true));
		$this->assertTrue(in_array(TestUnit2\Activity2::class, $classes), TestUnit2\Activity2::class." not in ".var_export($classes, true));
		$this->assertEquals(["route", "role"], $unit->getTagOrder(), "tag order");
		$items = iterator_to_array($unit->getCacheItems(), false);
		$this->assertEquals(2, count($items), "item count");
	}
}
