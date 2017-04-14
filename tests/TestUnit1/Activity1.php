<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit1;

use Sturdy\Activity\Annotation\Action;

class Activity1
{
	/**
	 * @Action(start=true, next="action2")
	 */
	public function action1()
	{
	}

	/**
	 * @Action(next="{1:action3,2:action4,3:action6}")
	 */
	public function action2()
	{
	}

	/**
	 * @Action(next="action7")
	 */
	public function action3()
	{
	}

	/**
	 * @Action(next="action5")
	 */
	public function action4()
	{
	}

	/**
	 * @Action(next="action7")
	 */
	public function action5()
	{
	}

	/**
	 * @Action(next="action7")
	 */
	public function action6()
	{
	}

	/**
	 * @Action(next="action8")
	 */
	public function action7()
	{
	}

	/**
	 * @Action(next="action9")
	 */
	public function action8()
	{
	}

	/**
	 * @Action(next="{true:action8,false:action10}")
	 */
	public function action9()
	{
	}

	/**
	 * @Action()
	 */
	public function action10()
	{
	}
}
