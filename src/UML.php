<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;
use Generator;
use stdClass;

final class UML
{
	private $colors;

	/**
	 * Set class color
	 *
	 * @param string $class color
	 * @return self
	 */
	public function setClassColor(string $class, string $color): self
	{
		$this->colors[$class] = $color;

		return $this;
	}

	/**
	 * Get class color
	 *
	 * @return string
	 */
	public function getClassColor(string $class): ?string
	{
		return $this->colors[$class]??null;
	}

	/**
	 * Generate colors for classes.
	 */
	public function generateClassColors(array $classes): void
	{
		$this->colors = array_merge(array_flip($classes), $this->colors??[]);
		foreach ($this->colors as &$color) {
			if (is_string($color)) continue;
			$i = 0;
			do {
				$r = dechex(random_int(7, 15)*16);
				if (strlen($r) == 1) $r = "0$r";
				$g = dechex(random_int(7, 15)*16);
				if (strlen($g) == 1) $g = "0$g";
				$b = dechex(random_int(7, 15)*16);
				if (strlen($b) == 1) $b = "0$b";
				$c = "#$r$g$b";
				if ($i++ > 20) break; // don't get stuck here
			} while (!in_array($c, $this->colors, true));
			$color = $c;
		}
	}

	/**
	 * Generate the UML for the activity diagram.
	 *
	 * @param $dimensions  key=>value array of dimensions
	 * @param $actions     the actions
	 */
	public function generate(array $dimensions, array $actions): string
	{
		$compiler = $this->compile($actions, "start");
		foreach ($compiler as $action); // simply run the compiler
		$branch = $compiler->getReturn();

		$uml = "@startuml\n";
		if (count($dimensions)) {
			$uml.= "floating note left\n";
			foreach ($dimensions as $dimension => $value) {
				if ($value === true) {
					$uml.= "\t$dimension\n";
				} elseif ($value !== null) {
					$uml.= "\t$dimension: $value\n";
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
		$p = strpos($action, "::");
		if ($p !== false) {
			$className = substr($action, 0, $p);
			$methodName = substr($action, $p+2);
			return $this->colors[$className].":$methodName|\n";
		} else {
			switch ($action) {
				case "start":
				case "stop":
				case "end":
					return "$action\n";
				default:
					return ":$action;\n";
			}
		}
	}
}
