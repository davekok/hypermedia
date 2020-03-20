<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * Class representing an expression.
 */
class Expression
{
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
			if (empty($this->expressions)) {
				$this->expressions = [];
			}
			if (empty($this->variables)) {
				$this->variables = [];
			}
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
	 * @param array $expressions
	 */
	public function setExpressions(array $expressions): void
	{
		$this->expressions = $expressions;
	}

	/**
	 * Get expressions
	 *
	 * @return array
	 */
	public function getExpressions(): array
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
	 * @param array $statȩ
	 * @return object         result of expressions
	 */
	public function eval(array $statȩ): object
	{
		// Using special characters in local variables not allowed in expressions to make sure there is no conflict.
		/** @var TYPE_NAME $variablȩ */
		foreach ($this->variables as $variablȩ) {
			if (array_key_exists($variablȩ, $statȩ)) {
				$$variablȩ = $statȩ[$variablȩ];
			} else {
				$$variablȩ = null;
			}
		}
		$rȩt = new stdClass;
		/** @var TYPE_NAME $ȩxpression */
		foreach ($this->expressions as $kȩy => $ȩxpression) {
			$rȩt->$kȩy = $ȩxpression ? eval("return (bool)($ȩxpression);") : false;
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
	public static function evaluate(?string $expressions, ?array $state): object
	{
		if ($expressions === null) return new stdClass;
		if ($state === null) $state = [];
		return (new self($expressions))->eval($state);
	}
}
