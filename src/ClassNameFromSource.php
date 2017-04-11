<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;

trait ClassNameFromSource
{
	/**
	 * Get the class defined in the PHP source.
	 *
	 * @param $source  the source to scan
	 * @return the full class name
	 */
	public function getClassNameFromSource(string $source): string
	{
		$className = "";
		$state = 0;
		foreach (token_get_all($source, TOKEN_PARSE) as $token) {
			switch ($state) {
				case 0; // find namespace or class
					if ($token[0] === T_NAMESPACE || $token[0] === T_CLASS) {
						$state = 1;
						$type = $token[0];
					}
					break;
				case 1: // append strings to $className until another token is found
					switch ($token[0]) {
						case T_WHITESPACE: // skip white space
							break;
						case T_STRING: // part of the class name
							$className .= $token[1];
							break;
						case T_NS_SEPARATOR: // name space separator
							$className .= '\\';
							break;
						default: // start of another token
							if ($type === T_CLASS) {
								return $className;
							}
							$className .= '\\';
							$state = 0;
							break;
					}
			}
		}
		throw new Exception("No class found in source.");
	}
}
