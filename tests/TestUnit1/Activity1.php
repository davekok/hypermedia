<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

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
	 * @Action(next="{1:action3,2:action4,3:action5}")
	 */
	public function action2()
	{
	}

	/**
	 * @Action(next="action6")
	 */
	public function action3()
	{
	}

	/**
	 * @Action(next="action6")
	 */
	public function action4()
	{
	}

	/**
	 * @Action(next="action6")
	 */
	public function action5()
	{
	}

	/**
	 * @Action(next="{true:action7,false:action9}")
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
	 * @Action(next="action6")
	 */
	public function action8()
	{
	}

	/**
	 * @Action()
	 */
	public function action9()
	{
	}
}
