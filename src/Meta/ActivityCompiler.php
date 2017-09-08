<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Exception;

/**
 * Compile a activity
 */
class ActivityCompiler
{
	private $activity;
	private $matcher;
	private $joins;
	private $join;
	private $found;

	/**
	 * Compile activity
	 *
	 * @return CacheItem_Activity
	 */
	public function compile(Activity $activity, TagMatcher $matcher): CacheItem_Activity
	{
		$this->activity = $activity;
		$this->matcher = $matcher;
		$this->joins = [];
		$this->join = 0;
		$this->found = [];
		$this->item = new CacheItem_Activity();
		$this->item->setClass($this->activity->getClass());
		$this->item->setTags($matcher->getTags());
		$key = "start"; // activities begin at the start action
		$this->walk($key);
		return $this->item;
	}

	/**
	 * Walk over the actions.
	 *
	 * @param string &$key  the action key
	 */
	private function walk(string &$key): void
	{
		$action = $this->matcher->findBestMatch($this->activity->getActionsWithName($key));
		if ($action === null) {
			$this->item->clear();
			return;
		}

		$hash = spl_object_hash($action);

		// return a join in case this is a join action
		if ($action->isJoin()) {
			if (isset($this->joins[$hash])) {
				$joinNumber = $this->joins[$hash];
			} else {
				$joinNumber = $this->joins[$hash] = ++$this->join;
			}
			$this->item->setAction($joinNumber, $key);
			$key = $joinNumber;
		}

		// already walked over this action? should only happen for loops and joins
		if (isset($this->found[$hash])) {
			return;
		} else {
			$this->found[$hash] = true;
		}

		// get the next action(s)
		$next = $action->getNext();

		$this->item->setAction($key, $next);

		// does the action have a decision?
		if (is_object($next)) {
			foreach ($next as $decision => &$expr) {
				if (is_string($expr)) { // expr is key of next action
					$this->walk($expr);
				} elseif (is_array($expr)) { // expr is array of keys, forking the activity
					foreach ($expr as $expr) {
						$this->walk($expr);
					}
				} elseif ($expr === false) { // expr is end of activity
					return;
				}
			}
		} elseif (is_string($next)) { // expr is key of next action
			$this->walk($next);
		} elseif (is_array($next)) { // fork actions
			foreach ($next as &$expr) { // expr is array of keys, forking the activity
				$this->walk($expr);
			}
		} elseif ($next === false) { // expr is end of activity
			return;
		}
	}
}
