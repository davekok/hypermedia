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
	public function testUml()
	{
		$dimensions = ["dim1"=>1,"dim2"=>2];
		$actions = [
			"start"=>"TestClass::action1",
			"TestClass::action1"=>"TestClass::action2",
			"TestClass::action2"=>[
				1=>"TestClass::action3",
				2=>"TestClass::action4",
				3=>"TestClass::action6",
			],
			"TestClass::action3"=>"TestClass::action7",
			"TestClass::action4"=>"TestClass::action5",
			"TestClass::action5"=>"TestClass::action7",
			"TestClass::action6"=>"TestClass::action7",
			"TestClass::action7"=>"TestClass::action8",
			"TestClass::action8"=>"TestClass::action9",
			"TestClass::action8"=>"TestClass::action9",
			"TestClass::action9"=>[
				"true"=>"TestClass::action8",
				"false"=>"TestClass::action10",
			],
			"TestClass::action10"=>null,
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
repeat
	#CCCCDD:action8|
	#CCCCDD:action9|
repeat while (r = true)
#CCCCDD:action10|
stop
@enduml

UML
		);
	}
}
