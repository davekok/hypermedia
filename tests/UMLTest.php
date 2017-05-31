<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\UML;
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument,
	Prophet
};

class UMLTest extends TestCase
{
	public function testIf()
	{
		$dimensions = ["dim1"=>1,"dim2"=>2];
		$actions = [
			"start"=>"TestClass::action1",
			"TestClass::action1"=>"TestClass::action2",
			"TestClass::action2"=>(object)[
				1=>"TestClass::action3",
				2=>"TestClass::action4",
				3=>"TestClass::action6",
			],
			"TestClass::action3"=>"TestClass::action7",
			"TestClass::action4"=>"TestClass::action5",
			"TestClass::action5"=>"TestClass::action7",
			"TestClass::action6"=>"TestClass::action7",
			"TestClass::action7"=>false,
		];

		$uml = new UML();
		$uml->setClassColor('TestClass', '#CCCCDD');
		$this->assertEquals($uml->generate($dimensions, $actions), <<<UML
@startuml
floating note left
	dim1: 1
	dim2: 2
end note
start
#CCCCDD:action1|
#CCCCDD:action2|
if (r) then (1)
	#CCCCDD:action3|
elseif (r) then (2)
	#CCCCDD:action4|
	#CCCCDD:action5|
else (3)
	#CCCCDD:action6|
endif
#CCCCDD:action7|
stop
@enduml

UML
		);
	}

	public function testRepeat()
	{
		$actions = [
			"start"=>"TestClass::action1",
			"TestClass::action1"=>"TestClass::action2",
			"TestClass::action2"=>"TestClass::action3",
			"TestClass::action3"=>"TestClass::action4",
			"TestClass::action4"=>(object)[
				"true"=>"TestClass::action2",
				"false"=>"TestClass::action5",
			],
			"TestClass::action5"=>false,
		];

		$uml = new UML();
		$uml->setClassColor('TestClass', '#CCCCDD');
		$this->assertEquals(<<<UML
@startuml
start
#CCCCDD:action1|
repeat
	#CCCCDD:action2|
	#CCCCDD:action3|
	#CCCCDD:action4|
repeat while (r = true)
#CCCCDD:action5|
stop
@enduml

UML
		, $uml->generate([], $actions));
	}

	public function testFork1()
	{
		$actions = [
			"start"=>"TestClass::action1",
			"TestClass::action1"=>"TestClass::action2",
			"TestClass::action2"=>[
				"TestClass::action3",
				"TestClass::action4",
				"TestClass::action5",
			],
			"TestClass::action3"=>"TestClass::action6",
			"TestClass::action4"=>"TestClass::action7",
			"TestClass::action5"=>"TestClass::action8",
			"TestClass::action6"=>"TestClass::action8",
			"TestClass::action7"=>"TestClass::action8",
			"TestClass::action8"=>false,
		];

		$uml = new UML();
		$uml->setClassColor('TestClass', '#CCCCDD');
		$this->assertEquals(<<<UML
@startuml
start
#CCCCDD:action1|
#CCCCDD:action2|
fork
	#CCCCDD:action3|
	#CCCCDD:action6|
fork again
	#CCCCDD:action4|
	#CCCCDD:action7|
fork again
	#CCCCDD:action5|
end fork
#CCCCDD:action8|
stop
@enduml

UML
		, $uml->generate([], $actions));
	}

	public function testFork2()
	{
		$actions = [
			"start"=>"TestClass::action1",
			"TestClass::action1"=>[
				"TestClass::action3",
				"TestClass::action4",
				"TestClass::action5",
			],
			"TestClass::action3"=>"TestClass::action6",
			"TestClass::action4"=>"TestClass::action7",
			"TestClass::action5"=>false,
			"TestClass::action6"=>false,
			"TestClass::action7"=>false,
		];

		$uml = new UML();
		$uml->setClassColor('TestClass', '#CCCCDD');
		$text = $uml->generate([], $actions);
		$this->assertEquals(<<<UML
@startuml
start
#CCCCDD:action1|
fork
	#CCCCDD:action3|
	#CCCCDD:action6|
fork again
	#CCCCDD:action4|
	#CCCCDD:action7|
fork again
	#CCCCDD:action5|
end fork
stop
@enduml

UML
		, $text);
	}
}
