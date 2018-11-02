<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Exception;
use Generator;
use stdClass;

final class UML
{
	/**
	 * Generate the UML for the activity diagram.
	 *
	 * @param $tags     key=>value array of tags
	 * @param $actions  the actions
	 */
	public function generate(array $tags, array $actions): string
	{
		$compiler = $this->compile($actions, "start");
		foreach ($compiler as $action); // simply run the compiler
		$branch = $compiler->getReturn();

		$uml = "@startuml\n";
		if (count($tags)) {
			$uml.= "floating note left\n";
			foreach ($tags as $tag => $value) {
				if ($value === true) {
					$uml.= "\t$tag\n";
				} elseif ($value !== null) {
					$uml.= "\t$tag: $value\n";
				}
			}
			$uml.= "end note\n";
		}
		$uml.= $this->writeBranch($branch);
		$uml.= "@enduml\n";
		return $uml;
	}

	/**
	 * Compile diagram
	 *
	 * @param $actions  actions to write
	 * @param $action   the next action
	 * @return the diagram
	 */
	private function compile(array $actions, string $action): Generator
	{
		$branch = [];
		$pastActions = [];
		$lastAction = $action;
		while (array_key_exists($action, $actions)) {
			$next = $actions[$action];
			if ($next === false) {
				$branch[] = $action;
				end($branch); $pastActions[$action] = key($branch);
				$action = $next;
			} elseif (is_string($next)) {
				$branch[] = $action;
				end($branch); $pastActions[$action] = key($branch);
				$action = $next;
			} elseif (is_array($next)) {
				$branch[] = $action;
				end($branch); $pastActions[$action] = key($branch);
				$line = new stdClass;
				reset($next);
				if (is_int(key($next))) {
					$line->type = "fork";
				} else {
					$line->type = "split";
				}
				[$action, $line->branches] = $this->parallel($next, $actions);
				$branch[] = $line;
			} elseif (is_object($next)) {
				$next = get_object_vars($next);
				switch (count($next)) {
					case 2:
						// check if it is a loop
						foreach ($next as $retval => $altaction) {
							if (isset($pastActions[$altaction])) {
								unset($next[$retval]);
								$next = reset($next);
								$line = new stdClass;
								$compiler = $this->compile($actions, $next);
								foreach ($compiler as $a);
								$tail = $compiler->getReturn();
								array_unshift($tail, $line);
								$line->type = "repeat";
								$line->isval = $retval;
								$line->action = $action;
								// remove portial from $branch and assign to $line->branch, replace that portion with $tial
								$line->branch = array_splice($branch, $pastActions[$altaction], count($branch), $tail);
								return $branch;
							}
						}
					default:
						$branch[] = $action;
						end($branch); $pastActions[$action] = key($branch);
						[$action, $branch[]] = $this->parallel($next, $actions);
				}
			} else {
				throw new Exception("Invalid diagram");
			}
			// yield current action for parallel compiling
			try {
				yield $action;
			} catch (Exception $e) {
				return $branch;
			}
			if ($action === $lastAction) {
				throw new Exception("next failed");
			} elseif ($action === false) {
				$branch[] = "stop";
				try {
					yield "stop";
				} catch (Exception $e) {} // suppress exception
				return $branch;
			} else {
				$lastAction = $action;
			}
		}
		throw new Exception("end not found");
	}

	/**
	 * Run multiple branches in parallel until common action is found, which is considered the join point.
	 * This is used for both alternative branches (if) and concurrent branches (fork).
	 *
	 * @param  array $next     the actions that kick of the parallel branches
	 * @param  array $actions  the actions to work from
	 * @return array           a tuple containing the join action and the branches
	 */
	private function parallel(array $next, array $actions): array
	{
		// create compilers
		$compilers = [];
		foreach ($next as $key => $paraction) {
			$compilers[$key] = $this->compile($actions, $paraction);
		}
		// run compilers in parallel until an action is found that is executed by all parallel branches
		$parallel = [];
		$branches = [];
		$method = 'rewind';
		while (1) {
			foreach ($compilers as $key => $compiler) {
				if (!$compiler->valid()) continue;
				$compiler->$method();
				if (!$compiler->valid()) { // compiler is done
					$branches[$key] = $compiler->getReturn();
					continue;
				}
				$action = $compiler->current();
				$parallel[$action][$key] = $key;
				if (count($parallel[$action]) === count($next)) {
					unset($parallel);
					// end of parallels found
					foreach ($compilers as $key => $compiler) {
						try {
							$compiler->throw(new \Exception("interrupt"));
						} catch (\Exception $e) {}
						while ($compiler->valid()) $compiler->next();
						$branches[$key] = $compiler->getReturn();
					}
					foreach ($branches as $key => &$parbranch) {
						$key = array_search($action, $parbranch);
						if ($key !== false) {
							$parbranch = array_slice($parbranch, 0, $key);
						}
					}
					break 2;
				}
			}
			$method = 'next';
		}
		return [$action, $branches];
	}

	/**
	 * Write branch
	 *
	 * @param $branch  branch to write
	 * @param $indent  indent to prefix lines with
	 * @return the branch in uml
	 */
	private function writeBranch(array $branch, string $indent = ""): string
	{
		$uml = "";
		foreach ($branch as $line) {
			if (is_string($line)) {
				$type = "action";
			} elseif (is_array($line)) {
				$type = "if";
			} else {
				$type = $line->type;
			}
			switch ($type) {
				case "action":
					$uml.= $indent.$this->formatAction($line);
					break;
				case "if":
					$i = 0;
					$l = count($line) - 1;
					foreach ($line as $retval => $branch) {
						if ($i === 0) {
							$uml.= $indent."if (r) then ($retval)\n";
						} elseif ($i === $l) {
							$uml.= $indent."else ($retval)\n";
						} else {
							$uml.= $indent."elseif (r) then ($retval)\n";
						}
						$uml.= $this->writeBranch($branch, "$indent\t");
						++$i;
					}
					$uml.= $indent."endif\n";
					break;
				case "repeat":
					$uml.= $indent."repeat\n";
					$uml.= $this->writeBranch($line->branch, "$indent\t");
					$uml.= $indent."\t".$this->formatAction($line->action);
					$uml.= $indent."repeat while (r = {$line->isval})\n";
					break;
				case "fork":
					$again = "";
					foreach ($line->branches as $branch) {
						$uml.= $indent."fork$again\n";
						$uml.= $this->writeBranch($branch, "$indent\t");
						$again = " again";
					}
					$uml.= $indent."end fork\n";
					break;
				case "split":
					$again = "";
					foreach ($line->branches as $branch) {
						$uml.= $indent."split$again\n";
						$uml.= $this->writeBranch($branch, "$indent\t");
						$again = " again";
					}
					$uml.= $indent."end split\n";
					break;
			}
		}
		return $uml;
	}

	/**
	 * Format a action.
	 *
	 * @param $action  the action to format
	 * @return the formatted line
	 */
	private function formatAction(string $action): string
	{
		switch ($action) {
			case "start":
			case "stop":
			case "end":
			case "exception":
				return "$action\n";
			default:
				return ":$action|\n";
		}
	}
}
