<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\UML;
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument,
	Prophet
};

class UMLTest extends TestCase
{
	public function testIf()
	{
		$tags = ["dim1"=>1,"dim2"=>2];
		$actions = [
			"start"=>"action1",
			"action1"=>"action2",
			"action2"=>(object)[
				1=>"action3",
				2=>"action4",
				3=>"action6",
			],
			"action3"=>"action7",
			"action4"=>"action5",
			"action5"=>"action7",
			"action6"=>"action7",
			"action7"=>false,
		];

		$uml = new UML();
		$this->assertEquals($uml->generate($tags, $actions), <<<UML
@startuml
floating note left
	dim1: 1
	dim2: 2
end note
start
:action1|
:action2|
if (r) then (1)
	:action3|
elseif (r) then (2)
	:action4|
	:action5|
else (3)
	:action6|
endif
:action7|
stop
@enduml

UML
		);
	}

	public function testRepeat()
	{
		$actions = [
			"start"=>"action1",
			"action1"=>"action2",
			"action2"=>"action3",
			"action3"=>"action4",
			"action4"=>(object)[
				"true"=>"action2",
				"false"=>"action5",
			],
			"action5"=>false,
		];

		$uml = new UML();
		$this->assertEquals(<<<UML
@startuml
start
:action1|
repeat
	:action2|
	:action3|
	:action4|
repeat while (r = true)
:action5|
stop
@enduml

UML
		, $uml->generate([], $actions));
	}

	public function testFork1()
	{
		$actions = [
			"start"=>"action1",
			"action1"=>"action2",
			"action2"=>[
				"action3",
				"action4",
				"action5",
			],
			"action3"=>"action6",
			"action4"=>"action7",
			"action5"=>"action8",
			"action6"=>"action8",
			"action7"=>"action8",
			"action8"=>false,
		];

		$uml = new UML();
		$this->assertEquals(<<<UML
@startuml
start
:action1|
:action2|
fork
	:action3|
	:action6|
fork again
	:action4|
	:action7|
fork again
	:action5|
end fork
:action8|
stop
@enduml

UML
		, $uml->generate([], $actions));
	}

	public function testFork2()
	{
		$actions = [
			"start"=>"action1",
			"action1"=>[
				"action3",
				"action4",
				"action5",
			],
			"action3"=>"action6",
			"action4"=>"action7",
			"action5"=>false,
			"action6"=>false,
			"action7"=>false,
		];

		$uml = new UML();
		$text = $uml->generate([], $actions);
		$this->assertEquals(<<<UML
@startuml
start
:action1|
fork
	:action3|
	:action6|
fork again
	:action4|
	:action7|
fork again
	:action5|
end fork
stop
@enduml

UML
		, $text);
	}

	public function testSplit1()
	{
		$actions = [
			"start"=>"action1",
			"action1"=>"action2",
			"action2"=>[
				"branch1"=>"action3",
				"branch2"=>"action4",
				"branch3"=>"action5",
			],
			"action3"=>"action6",
			"action4"=>"action7",
			"action5"=>"action8",
			"action6"=>"action8",
			"action7"=>"action8",
			"action8"=>false,
		];

		$uml = new UML();
		$this->assertEquals(<<<UML
@startuml
start
:action1|
:action2|
split
	:action3|
	:action6|
split again
	:action4|
	:action7|
split again
	:action5|
end split
:action8|
stop
@enduml

UML
		, $uml->generate([], $actions));
	}

	public function testSplit2()
	{
		$actions = [
			"start"=>"action1",
			"action1"=>[
				"branch1"=>"action3",
				"branch2"=>"action4",
				"branch3"=>"action5",
			],
			"action3"=>"action6",
			"action4"=>"action7",
			"action5"=>false,
			"action6"=>false,
			"action7"=>false,
		];

		$uml = new UML();
		$text = $uml->generate([], $actions);
		$this->assertEquals(<<<UML
@startuml
start
:action1|
split
	:action3|
	:action6|
split again
	:action4|
	:action7|
split again
	:action5|
end split
stop
@enduml

UML
		, $text);
	}
}
