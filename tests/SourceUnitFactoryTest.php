<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\UnitFactory;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Annotations\AnnotationReader;

class SourceUnitFactoryTest extends TestCase
{
	public function testCreateUnit1()
	{
		$unit = (new SourceUnitFactory(new AnnotationReader))->createUnitFromSource('TestUnit1', __DIR__.'/TestUnit1/');
		$this->assertEquals($unit->getName(), "TestUnit1", "unit name");
		$this->assertEquals($unit->getClasses(), [TestUnit1\Activity1::class], "classes");
		$this->assertEquals($unit->getDimensions(), [], "dimensions");
		$this->assertEquals($unit->getActions(), [
			'start' => [
				(object)['next'=>TestUnit1\Activity1::class.'::action1','dimensions'=>[],'const'=>true],
			],
			TestUnit1\Activity1::class.'::action1' => [
				(object)['next'=>TestUnit1\Activity1::class.'::action2','dimensions'=>[],'const'=>false],
			],
			TestUnit1\Activity1::class.'::action2' => [
				(object)[
					'next'=>[
						1 => TestUnit1\Activity1::class.'::action3',
						2 => TestUnit1\Activity1::class.'::action4',
						3 => TestUnit1\Activity1::class.'::action6',
					],
					'dimensions'=>[],
					'const'=>false
				],
			],
			TestUnit1\Activity1::class.'::action3' => [
				(object)['next'=>TestUnit1\Activity1::class.'::action7','dimensions'=>[],'const'=>false],
			],
			TestUnit1\Activity1::class.'::action4' => [
				(object)['next'=>TestUnit1\Activity1::class.'::action5','dimensions'=>[],'const'=>false]
			],
			TestUnit1\Activity1::class.'::action5' => [
				(object)['next'=>TestUnit1\Activity1::class.'::action7','dimensions'=>[],'const'=>true]
			],
			TestUnit1\Activity1::class.'::action6' => [
				(object)['next'=>TestUnit1\Activity1::class.'::action7','dimensions'=>[],'const'=>false]
			],
			TestUnit1\Activity1::class.'::action7' => [
				(object)['next'=>TestUnit1\Activity1::class.'::action8','dimensions'=>[],'const'=>false]
			],
			TestUnit1\Activity1::class.'::action8' => [
				(object)['next'=>TestUnit1\Activity1::class.'::action9','dimensions'=>[],'const'=>false]
			],
			TestUnit1\Activity1::class.'::action9' => [
				(object)[
					'next'=>[
						'true' => TestUnit1\Activity1::class.'::action8',
						'false' => TestUnit1\Activity1::class.'::action10',
					],
					'dimensions'=>[],
					'const'=>false
				],
			],
			TestUnit1\Activity1::class.'::action10' => [
				(object)[
					'next'=>null,
					'dimensions'=>[],
					'const'=>false
				],
			]
		], "actions");
	}

	public function testCreateUnit2()
	{
		$unit = (new SourceUnitFactory(new AnnotationReader))->createUnitFromSource('TestUnit2', __DIR__.'/TestUnit2/');
		$this->assertEquals($unit->getName(), "TestUnit2", "unit name");
		$classes = $unit->getClasses();
		$this->assertTrue(in_array(TestUnit2\Activity1::class, $classes));
		$this->assertTrue(in_array(TestUnit2\Activity2::class, $classes));
		$this->assertEquals($unit->getDimensions(), ["route", "role"], "dimensions");
		$activities = $unit->getActivities();
		$this->assertEquals(count($activities), 2);
		$this->assertTrue($activities[0]->const xor $activities[1]->const);
	}
}
