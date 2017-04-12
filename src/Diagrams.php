<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;

final class Diagrams
{
	use MkDir;

	private $docDir;
	private $fileMask;
	private $colors;

	/**
	 * Constructor
	 */
	public function __construct(string $docDir = null, int $fileMask = 00002)
	{
		$this->docDir = $this->filterDir($docDir, 'doc');
		$this->fileMask = $fileMask;
	}

	/**
	 * Generate class colors for classes.
	 */
	public function generateClassColors()
	{
		$this->colors = array_flip($this->unit->getClasses());
		foreach ($this->colors as &$color) {
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
	 * Write all activities of the unit to document directory.
	 *
	 * @param $unit  the unit to create diagrams for
	 */
	public function write(Unit $unit): void
	{
		$this->unit = $unit;

		$docDir = $this->mkdir($this->docDir.DIRECTORY_SEPARATOR.$this->unit->getName(), $this->fileMask);

		$this->generateClassColors();

		foreach ($this->unit->getActions() as $dimensions => $actions) {
			$generator = $this->compile($actions, "start");
			foreach ($generator as $v);
			$diagram = $generator->getReturn();

			$filename = trim("activity ".implode(" ",$dimensions?:[]));

			$old = umask($this->fileMask);
			$file = fopen($docDir.DIRECTORY_SEPARATOR.$filename.".uml", "w");
			umask($old);

			fwrite($file, "@startuml\n");
			$this->writeDiagram($file, $diagram);
			fwrite($file, "@enduml\n");
			fclose($file);
		}
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
		$banch = [];
		$pastActions = [];
		while (isset($actions[$action])) {
			$next = $actions[$action];
			if (empty($next)) {
				$banch[] = $action;
				end($banch); $pastActions[$action] = key($banch);
				$banch[] = "stop";
				yield "stop";
				break;
			} elseif (is_string($next)) {
				$banch[] = $action;
				end($banch); $pastActions[$action] = key($banch);
				$action = $next;
			} elseif (is_array($next)) {
				switch (count($next)) {
					case 2:
						// check if it is a loop
						foreach ($next as $retval => $altaction) {
							if (isset($pastActions[$altaction])) {
								unset($next[$retval]);
								$next = reset($next);
								$line = new stdClass;
								$tail = $this->compile($actions, $next);
								array_unshift($tail, $repeat);
								$line->type = "repeat";
								$line->isval = $retval;
								$line->action = $action;
								$line->branch = array_splice($branch, $pastActions[$altaction], count($branch), $tail);
								return $banch;
							}
						}
					default:
						$banch[] = $action;
						end($banch); $pastActions[$action] = key($banch);
						// create compilers
						$compilers = [];
						foreach ($next as $retval => $altaction) {
							$compilers[$retval] = $this->compile($actions, $altaction);
						}
						// run compilers in parallel until an action is found that is executed by all branches
						$alts = [];
						$branches = [];
						$activecompilers = $compilers;
						$method = 'rewind';
						while (1) {
							foreach ($activecompilers as $retval => &$compiler) {
								$compiler->$method();
								if (!$compiler->valid()) {
									unset($activecompilers[$retval]);
								}
								$action = $compiler->current();
								$alts[$action][$retval] = $retval;
								if (count($alts[$action]) === count($next)) {
									// end of altenatives found
									foreach ($activecompilers as $compiler) {
										$compiler->throw(new \Exception());
									}
									foreach ($compilers as $compiler) {
										$branches[$retval] = $compiler->getReturn();
									}
									break 2;
								}
							}
							$method = 'next';
						}
						$banch[] = $branches;
				}
			} else {
				throw new \Exception("Invalid diagram");
			}
			// yield current action for parallel compiling
			try {
				yield $action;
			} catch (\Exception $e) {
				break;
			}
		}
		return $branch;
	}

	/**
	 * Write branch
	 *
	 * @param $file    file resource to write to
	 * @param $branch  branch to write
	 * @param $indent  indent to prefix lines with
	 * @return the formatted action
	 */
	private function writeDiagram($file, array $branch, string $indent = ""): void
	{
		foreach ($branch as [$type, $value]) {
			switch ($type) {
			case "action":
				$p = strpos($value, "::");
				$className = substr($value, 0, $p);
				$methodName = substr($value, $p+2);
				fwrite($file, $indent.$this->colors[$className].":$methodName;\n");
				break;
			case "if":
				$i = 0;
				$l = count($value) - 1;
				foreach ($value as $retval => $branch) {
					switch ($i++) {
						case 0:
							fwrite($file, $indent."if () then ($retval)\n");
							break;
						case $l:
							fwrite($file, $indent."else ($retval)\n");
							break;
						default:
							fwrite($file, $indent."elseif () then ($retval)\n");
							break;
					}
					$this->writeDiagram($file, $branch, "$indent\t");
				}
				fwrite($file, $indent."endif\n");
				break;
			case "repeat":
				[$action, $branch] = $value;
				fwrite($file, $indent."repeat\n");
				$this->writeDiagram($file, $branch, "$indent\t");
				fwrite($file, $indent."repeat while ($action)\n");
				break;
			}
		}
	}

	/**
	 * Format an action.
	 *
	 * @param $action  the action to format
	 * @return the formatted action
	 */
	private function getColor(string $action): string
	{
		return $this->colors[substr($action, 0, strpos($action, "::"))];
	}
}
