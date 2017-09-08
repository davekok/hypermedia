<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\Action;
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument,
	Prophet
};

/**
 * Implementation of ActionTest
 */
class ActionTest extends TestCase
{
	public function testEmpty()
	{
		try {
			$action = new Action();
			$action->parse();
			$this->assertFalse($action->getStart());
			$this->assertFalse($action->isJoin());
			$this->assertFalse($action->hasReturnValues());
			$this->assertNull($action->getNext());
			$this->assertEquals("", "$action");
		} catch (Throwable $e) {
			$this->fail($e->getMessage());
		}
	}

	public function testName()
	{
		$action = new Action();
		$action->setText("[action1] end");
		$action->parse();
		$this->assertEquals("action1", $action->getName());
	}

	public function testStart()
	{
		$action = new Action();
		$action->setText("start end");
		$action->parse();
		$this->assertTrue($action->getStart());
	}

	public function testJoin()
	{
		$action = new Action();
		$action->setText(">| end");
		$action->parse();
		$this->assertTrue($action->isJoin());
	}

	public function testEnd()
	{
		$action = new Action();
		$action->setText("end");
		$action->parse();
		$this->assertFalse($action->getNext());
	}

	public function testNextAlreadyDefined()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText("end > action1");
		try {
			$action->parse();
			$this->fail();
		} catch (\Throwable $e) {
			$this->assertEquals("Unexpected token\nend > action1\n    ^\n", $e->getMessage());
		}
	}

	public function testNextAction()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText("> action");
		$action->parse();
		$this->assertEquals("action", $action->getNext());
	}

	public function testNextFork()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText('> action1 | action2 | action3');
		$action->parse();
		$this->assertEquals(["action1", "action2", "action3"], $action->getNext());
	}

	public function testNextSplit1()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText('> rel:action1');
		$action->parse();
		$this->assertEquals(["rel"=>"action1"], $action->getNext());
	}

	public function testNextSplitN()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText('> next:action1 | prev:action2 | end:action3');
		$action->parse();
		$this->assertEquals(["next"=>"action1", "prev"=>"action2", "end"=>"action3"], $action->getNext());
	}

	public function testBooleanReturnValues()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText("+>end  ->action2");
		$action->parse();
		$this->assertEquals((object)["+"=>false, "-"=>"action2"], $action->getNext());
		$this->assertTrue($action->hasReturnValues());
	}

	public function testIntegerReturnValues()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText("0> end  1> action2  2> branch1:action3 | branch2:action4  3> action5 | action6");
		$action->parse();
		$this->assertEquals((object)[0=>false, 1=>"action2", 2=>["branch1"=>"action3", "branch2"=>"action4"], 3=>["action5","action6"]], $action->getNext());
		$this->assertTrue($action->hasReturnValues());
	}

	public function testTooFewReturnValues()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText("0> end");
		try {
			$action->parse();
			$this->fail('no exception thrown');
		} catch (\Throwable $e) {
			$this->assertEquals("Expected if int token\n0> end\n      ^\n", $e->getMessage());
		}
	}

	public function testMixedReturnValues()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText("0> end +> action2");
		try {
			$action->parse();
			$this->fail();
		} catch (\Throwable $e) {
			$this->assertEquals("Unexpected token\n0> end +> action2\n".str_repeat(" ", 7)."^\n", $e->getMessage());
		}
	}

	public function testNextForkReturnValues()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText('+> action1 | action2 | action3  -> end');
		$action->parse();
		$this->assertEquals((object)["+"=>["action1", "action2", "action3"], "-"=>false], $action->getNext());
		$this->assertTrue($action->hasReturnValues());
	}

	public function testNextSplitReturnValues()
	{
		$action = new Action();
		$action->setName("bar");
		$action->setText('+> rel1:action1 | rel2:action2 | rel3:action3  -> end');
		$action->parse();
		$this->assertEquals((object)["+"=>["rel1"=>"action1", "rel2"=>"action2", "rel3"=>"action3"], "-"=>false], $action->getNext());
		$this->assertTrue($action->hasReturnValues());
	}

	public function testTags()
	{
		$action = new Action();
		$action->setText('#foo=bar #baz= #bas ');
		$action->parse();
		$this->assertEquals(["foo"=>"bar", "baz"=>null, "bas"=>true], $action->getTags());
	}

	public function testMultilineInDocBlock()
	{
		$action = new Action();
		$action->setText("#foo=bar
			*    #baz=
			*    #bas
			* ");
		$action->parse();
		$this->assertEquals(["foo"=>"bar", "baz"=>null, "bas"=>true], $action->getTags());
	}

	public function testToString()
	{
		$action = Action::createFromText('[action1] > action2 #foo=bar #baz= #bas');
		$this->assertEquals('[action1] > action2 #foo=bar #baz= #bas', "$action");
	}
}
