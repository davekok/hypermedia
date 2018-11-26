<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * Class representing an expression.
 */
class Expression
{
	private const SEPARATOR1 = "#";
	private const SEPARATOR2 = ",";

	/**
	 * @var array
	 */
	private $expressions;

	/**
	 * @var array
	 */
	private $variables;

	/**
	 * Constructor
	 *
	 * If variables is null, expressions is expected to be a serialized string
	 * as proceduced by the __toString function.
	 *
	 * @param string|array|null $expressions  expressions
	 * @param array|null        $variables    variables in expression
	 */
	public function __construct($expressions = null, array $variables = null)
	{
		if (is_array($expressions) && is_array($variables)) {
			$this->expressions = $expressions;
			$this->variables = $variables;
		} else if (is_string($expressions) && $variables === null) {
			[$this->expressions, $this->variables] = unserialize($expressions);
		} else {
			$this->expressions = [];
			$this->variables = [];
		}
	}

	/**
	 * Convert expression to string
	 */
	public function __toString(): string
	{
		return serialize([$this->expressions, $this->variables]);
	}

	/**
	 * Set expressions
	 *
	 * @param string $expressions
	 */
	public function setExpressions(array $expressions): void
	{
		$this->expressions = $expressions;
	}

	/**
	 * Get expressions
	 *
	 * @return string
	 */
	public function getExpressions(): string
	{
		return $this->expressions;
	}

	/**
	 * Set variables
	 *
	 * @param array $variables
	 */
	public function setVariables(array $variables): void
	{
		$this->variables = $variables;
	}

	/**
	 * Get variables
	 *
	 * @return array
	 */
	public function getVariables(): array
	{
		return $this->variables;
	}

	/**
	 * Evalutate expressions with state.
	 *
	 * @param  array $state   the object containing state
	 * @return object         result of expressions
	 */
	public function eval(array $statȩ): object
	{
		// Using special characters in local variables not allowed in expressions to make sure there is no conflict.
		foreach ($this->variables as $variablȩ) {
			if (array_key_exists($variablȩ, $statȩ)) {
				$$variablȩ = $statȩ->$variablȩ;
			} else {
				throw new \LogicException("$variablȩ missing in state");
			}
		}
		$rȩt = new stdClass;
		foreach ($this->expressions as $kȩy => $ȩxpression) {
			$rȩt->$kȩy = eval("return $ȩxpression;");
		}
		return $rȩt;
	}

	/**
	 * Evalutate expression with state.
	 *
	 * @param  string $expressions  serialized expressions
	 * @param  array|null $state    the object containing state
	 * @return object               result of expression
	 */
	public static function evaluate(?string $expressions, ?array $state): array
	{
		if ($expressions === null) return [];
		if ($state === null) $state = [];
		return (new self($expressions))->eval($state);
	}
}
