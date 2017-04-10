<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;

class ActivityDiagram
{
	use Utils;

	private $unit;
	private $colors;

	public function __construct(Unit $unit)
	{
		$this->unit = $unit;
	}

	/**
	 * Generate class colors for classes.
	 */
	public function generateClassColors()
	{
		$this->colors = array_flip($unit->getClasses());
		foreach ($colors as &$color) {
			$i = 0;
			do {
				$r = dechex(random_int(0, 15)*16);
				if (strlen($r) == 1) $r = "0$r";
				$g = dechex(random_int(0, 15)*16);
				if (strlen($g) == 1) $g = "0$g";
				$b = dechex(random_int(0, 15)*16);
				if (strlen($b) == 1) $b = "0$b";
				$c = "#$r$g$b";
				if ($i++ > 20) break; // don't get stuck here
			} while (!in_array($c, $classes, true));
			$color = $c;
		}
	}

	/**
	 * Write activities to document directory.
	 *
	 * @param $docDir  directory to write to
	 */
	public function write(string $docDir): void
	{
		$docDir = $docDir."/".$unit->getName();
		$this->mkdir($docDir);
		$this->generateClassColors();
		foreach ($unit->getActions() as $dimensions => $actions) {
			$diagram = [];
			$diagram[] = "start";
			$action = "start";
			$pastActions = [];
			while (isset($actions[$action])) {
				$next = $actions[$action];
				switch (count($next)) {
					case 0:
						$diagram[] = ["stop"];
						break;
					case 1:
						$action = $next;
						$diagram[] = ["action", $action];
						end($diagram);
						$pastActions[$action] = &$diagram[key($diagram)];
						break;
					case 2:
						$diagram[] = ["if", $action];
						break;
					default:
						break;
					}
				}
			}

			$file = fopen($docDir."/activity ".implode(" ",$dimensions).".uml", "w");
			fwrite($file, "@startuml\n");
			$this->writeDiagram($file, $diagram);
			fwrite($file, "@enduml\n");
			fclose($file);
		}
	}

	/**
	 * Format an action.
	 *
	 * @param $action  the action to format
	 * @return the formatted action
	 */
	private function writeDiagram(resource $file, array $diagram, string $indent = ""): void
	{
		foreach ($diagram as [$type, $value]) {
			switch ($type) {
			case "action":
				$className = substr($action, 0, strpos($action, "::"));
				fwrite($file, $indent.$this->colors[$className].":$action;\n");
				break;
			case "if":
				$i = 0;
				$l = count($value) - 1;
				foreach ($value as $value => $branch) {
					switch ($i++) {
						case 0:
							fwrite($file, $indent."if () then ($value)\n");
							break;
						case $l:
							fwrite($file, $indent."else ($value)\n");
							break;
						default:
							fwrite($file, $indent."elseif () then ($value)\n");
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
