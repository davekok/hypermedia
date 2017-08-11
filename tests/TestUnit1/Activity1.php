<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit1;

use Sturdy\Activity\{Action,Activity};

class Activity1
{
	/**
	 * @Action("start >action2")
	 */
	public function action1(Activity $activity)
	{
	}

	/**
	 * @Action("
	 *    1> action3
	 *    2> action4
	 *    3> action6
	 * ")
	 */
	public function action2(Activity $activity)
	{
	}

	/**
	 * @Action(">action7")
	 */
	public function action3(Activity $activity)
	{
	}

	/**
	 * @Action(">action5")
	 */
	public function action4(Activity $activity)
	{
	}

	/**
	 * @Action(">action7")
	 */
	public function action5(Activity $activity)
	{
	}

	/**
	 * @Action(">action7")
	 */
	public function action6(Activity $activity)
	{
	}

	/**
	 * @Action(">action8")
	 */
	public function action7(Activity $activity)
	{
	}

	/**
	 * @Action(">action9")
	 */
	public function action8(Activity $activity)
	{
	}

	/**
	 * @Action("+>action8  ->action10")
	 */
	public function action9(Activity $activity)
	{
	}

	/**
	 * @Action(">action11|action13|action15")
	 */
	public function action10(Activity $activity)
	{
	}

	/**
	 * @Action(">action12")
	 */
	public function action11(Activity $activity)
	{
	}

	/**
	 * @Action(">action17")
	 */
	public function action12(Activity $activity)
	{
	}

	/**
	 * @Action(">action14")
	 */
	public function action13(Activity $activity)
	{
	}

	/**
	 * @Action(">action17")
	 */
	public function action14(Activity $activity)
	{
	}

	/**
	 * @Action(">action16")
	 */
	public function action15(Activity $activity)
	{
	}

	/**
	 * @Action(">action17")
	 */
	public function action16(Activity $activity)
	{
	}

	/**
	 * @Action(">| >action18")
	 */
	public function action17(Activity $activity)
	{
	}

	/**
	 * @Action("> branch1:action19|branch2:action21|branch3:action23")
	 */
	public function action18(Activity $activity)
	{
	}

	/**
	 * @Action(">action20")
	 */
	public function action19(Activity $activity)
	{
	}

	/**
	 * @Action(">action25")
	 */
	public function action20(Activity $activity)
	{
	}

	/**
	 * @Action(">action22")
	 */
	public function action21(Activity $activity)
	{
	}

	/**
	 * @Action(">action25")
	 */
	public function action22(Activity $activity)
	{
	}

	/**
	 * @Action(">action24")
	 */
	public function action23(Activity $activity)
	{
	}

	/**
	 * @Action(">action25")
	 */
	public function action24(Activity $activity)
	{
	}

	/**
	 * @Action(">| end")
	 */
	public function action25(Activity $activity)
	{
	}
}
