<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit1;

use Sturdy\Activity\Action;

class Activity1
{
	/**
	 * @Action("start >action2")
	 */
	public function action1()
	{
	}

	/**
	 * @Action(">action3|action4|action6")
	 */
	public function action2()
	{
	}

	/**
	 * @Action(">action7")
	 */
	public function action3()
	{
	}

	/**
	 * @Action(">action5")
	 */
	public function action4()
	{
	}

	/**
	 * @Action(">action7")
	 */
	public function action5()
	{
	}

	/**
	 * @Action(">action7")
	 */
	public function action6()
	{
	}

	/**
	 * @Action(">| >action8")
	 */
	public function action7()
	{
	}

	/**
	 * @Action(">action9")
	 */
	public function action8()
	{
	}

	/**
	 * @Action("+>action8  ->action10")
	 */
	public function action9()
	{
	}

	/**
	 * @Action("end")
	 */
	public function action10()
	{
	}
}
