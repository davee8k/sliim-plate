<?php
declare(strict_types=1);

namespace SlimPlate;

use InvalidArgumentException;

/**
 * Bare bone templating system for text strings, based on Latte syntax
 * Self-fixing >< conversion by HTML editors (TinyMCE)
 * Support $VARIABLES, "VALUES", IF with one condition, ELSE and
 * That's all Folks!
 *
 * <code>{if $a->b == 5}OK{else}NO{/if}</code>
 *
 * @author DaVee8k
 * @license https://unlicense.org/
 * @version 0.87.2
 */
class SlimPlate
{
	/** @var string */
	protected static $regexIf = '/\{if((?:(?!\{if).)*)\}((?:(?!\{if).)+)\{\/if\}/iU';
	/** @var string */
	protected static $regexCond = '\$([a-z][a-z\d_]*)(?:(?:->|-&gt;)([a-z][a-z\d_]*))';
	/** @var string */
	protected $template = '';
	/** @var array<int, array<int, string>> */
	protected $matchData = null;

	/**
	 * SlimPlate constructor
	 * @param string $template
	 */
	public function __construct(string $template)
	{
		$this->template = $template;
	}

	/**
	 * Check template for errors, throw InvalidArgumentException
	 * @param mixed[] $data
	 * @param bool $strict
	 * @return true
	 * @throws InvalidArgumentException
	 */
	public function check(array $data = [], bool $strict = false): bool
	{
		$text = $this->template;
		while ($text && preg_match_all(self::$regexIf, $text, $matches)) {
			if (!empty($matches[0])) {
				foreach ($matches[0] as $i => $match) {
					if (!$text) break;
					$text = preg_replace('/'.preg_quote($match, '/').'/', '', $text, 1);
					$this->checkCondition($data, $matches[1][$i], $matches[2][$i]);
				}
			}
		}
		return empty($data) ?: $this->checkVariables($data, $strict);
	}

	/**
	 * Render template with current $data
	 * @param mixed[] $data
	 * @return string
	 */
	public function render(array $data = []): string
	{
		$text = $this->template;
		while ($text && preg_match_all(self::$regexIf, $text, $matches)) {
			if (!empty($matches[0])) {
				foreach ($matches[0] as $i => $match) {
					if (!$text) break;
					try {
						$text = $this->convertCondition($text, $data, $match, $matches[1][$i], $matches[2][$i]);
					} catch (InvalidArgumentException $e) {
						// ignore errors
					}
					$text = preg_replace('/'.preg_quote($match, '/').'/', '&#'.ord('{').';'.substr($match, 1, -1).'&#'.ord('}').';', $text, 1);
				}
			}
		}

		if ($text) {
			return $data ? $this->convertVariables($text, $data) : $text;
		}
		return '';
	}

	/**
	 * Check correct syntax inside conditions
	 * @param mixed[] $data
	 * @param string $condition
	 * @param string $fullCon
	 * @throws InvalidArgumentException
	 */
	protected function checkCondition(array $data, string $condition, string $fullCon): void
	{
		if (preg_match_all('/{else}/i', $fullCon) > 1) {
			throw new InvalidArgumentException("Multiple else in: ".$fullCon);
		}

		$con = trim($condition);
		$val = $this->parseUknown($data, $con);
		if ($val) $con = $this->removeRegex($con, (string) $val[0]);
		else throw new InvalidArgumentException('Empty IF condition');

		if ($con) {
			$operator = $this->findOperator($con);
			if ($operator && $this->operator(0, $operator[0], 0) !== null) {
				$con = $this->removeRegex($con, $operator[0]);
			}
			else {
				throw new InvalidArgumentException("Undefined IF operator: ".$con);
			}

			$val = $this->parseUknown($data, $con);
			if (!$val) throw new InvalidArgumentException('Missing second variable: '.trim($condition));
		}
	}

	/**
	 * Parse and convert condition in inserted text
	 * @param string $text
	 * @param mixed[] $data
	 * @param string $match
	 * @param string $condition
	 * @param string $fullCon
	 * @return string
	 */
	protected function convertCondition(string $text, array $data, string $match, string $condition, string $fullCon): string
	{
		$con = trim($condition);
		$firstVal = $this->parseUknown($data, $con);
		if ($firstVal) {
			$con = $this->removeRegex($con, (string) $firstVal[0]);

			if ($con) {
				$operator = $this->findOperator($con);
				if ($operator) {
					$con = $this->removeRegex($con, $operator[0]);

					$secVal = $this->parseUknown($data, $con);
					if ($secVal) {
						$con = $this->removeRegex($con, (string) $secVal[0]);

						$show = $this->operator($firstVal[1], $operator[0], $secVal[1]);
						if (!$con && $show !== null) {
							$text = preg_replace('/'.preg_quote($match, '/').'/', $this->decideIfElse($show, $fullCon), $text, 1);
						}
					}
				}
			} else {
				$text = preg_replace('/'.preg_quote($match, '/').'/', $this->decideIfElse(boolval($firstVal[1]), $fullCon), $text, 1);
			}
		}
		return $text ?? '';
	}

	/**
	 * Checks all variables in text against given ones
	 * @param mixed[] $data
	 * @param bool $strict
	 * @return true
	 * @throws InvalidArgumentException
	 */
	protected function checkVariables(array $data, bool $strict = false): bool
	{
		$notFound = $data;
		foreach ($this->matchVariables() as $match) {
			if (count($match) === 2 && isset($notFound[$match[1]])) {
				unset($notFound[$match[1]]);
			} elseif (count($match) === 3 && isset($notFound[$match[1]][$match[2]])) {
				unset($notFound[$match[1]][$match[2]]);
				if (empty($notFound[$match[1]])) unset($notFound[$match[1]]);
			} else {
				throw new InvalidArgumentException("Unset variable: ".$match[0]);
			}
		}

		if ($strict && !empty($notFound)) {
			throw new InvalidArgumentException("Unused variables: ".implode(',', array_keys($notFound)));
		}
		return true;
	}

	/**
	 * Replace variables in text
	 * @param string $text
	 * @param mixed[] $data
	 * @return string
	 */
	protected function convertVariables(string $text, array $data): string
	{
		foreach ($this->matchVariables() as $match) {
			if ($text) {
				if (count($match) === 2 && isset($data[$match[1]])) {
					$text = preg_replace('/'.preg_quote((string) $match[0]).'/', (string) $data[$match[1]], $text, 1);
				} elseif (count($match) === 3 && isset($data[$match[1]][$match[2]])) {
					$text = preg_replace('/'.preg_quote((string) $match[0]).'/', (string) $data[$match[1]][$match[2]], $text, 1);
				}
			}
		}
		return $text ?? '';
	}

	/**
	 * Tries to parse variable / value from condition
	 * @param mixed[] $data
	 * @param string $con
	 * @return array<int|float|string>|null
	 */
	protected function parseUknown(array $data, string $con): ?array
	{
		if ($con !== '') {
			if ($con[0] === '$') return $this->findVar($con, $data);
			return $this->findValue($con);
		}
		return null;
	}

	/**
	 * Find all variables {$var} in template
	 * @return array<int, array<int, string>>
	 */
	protected function matchVariables(): array
	{
		if ($this->matchData === null) {
			preg_match_all('/\{'.self::$regexCond.'?\}/iU', $this->template, $this->matchData, PREG_SET_ORDER);
		}
		return $this->matchData;
	}

	/**
	 * Get variable name from the condition and its value
	 * @param string $val
	 * @param mixed[] $data
	 * @return string[]
	 * @throws InvalidArgumentException
	 */
	protected function findVar(string $val, array $data): array
	{
		preg_match('/^'.self::$regexCond.'?/i', $val, $vars);
		if (empty($vars)) throw new InvalidArgumentException("Wrong variable definition");

		if ($data) {
			if (count($vars) === 2 && isset($data[$vars[1]])) $vars[1] = $data[$vars[1]];
			elseif (count($vars) === 3 && isset($data[$vars[1]][$vars[2]])) $vars[1] = $data[$vars[1]][$vars[2]];
			else throw new InvalidArgumentException("Undefined variable: ".$vars[0]);
		}
		return $vars;
	}

	/**
	 * Finds a comparison function
	 * @param string $val
	 * @return string[]
	 */
	protected function findOperator(string $val): array
	{
		preg_match('/^([=<>]|&gt;|&lt;)+/i', $val, $operator);
		return $operator;
	}

	/**
	 * Get value from condition
	 * @param string $val
	 * @return array<int|float|string>
	 */
	protected function findValue(string $val): array
	{
		if ($val[0] === '"') return $this->readValueCon($val, '"');
		elseif ($val[0] === "'") return $this->readValueCon($val, "'");
		elseif (substr($val[0], 0, 6) === '&quot;') return $this->readValueCon($val, '&quot;');
		elseif (substr($val[0], 0, 6) === '&#039;') return $this->readValueCon($val, '&#039;');
		return $this->readValueCon($val, null);
	}

	/**
	 * Return value
	 * @param string $val
	 * @param string|null $sep
	 * @return array<int|float|string>
	 * @throws InvalidArgumentException
	 */
	protected function readValueCon(string $val, ?string $sep): array
	{
		if ($sep !== null) {
			$sep = preg_quote($sep, '/');
			preg_match('/^'.$sep.'(.*)(?!\\\\'.$sep.').'.$sep.'/iU', $val, $matches);
			if ($matches) return $matches;
		} else {
			preg_match('/( |=|<|>|&lt;|&gt;)/i', $val, $matches);
			if ($matches) return $this->convertValueCon(substr($val, 0, (int) strpos($val, $matches[0])));
			return $this->convertValueCon($val);
		}
		throw new InvalidArgumentException("Undefined value: ".$val);
	}

	/**
	 * Convert found value into correct type
	 * @param string $val
	 * @return array<int|float|string>
	 */
	protected function convertValueCon(string $val): array
	{
		$convert = $val;
		if (is_numeric($val)) {
			$convert = filter_var($val, FILTER_VALIDATE_INT) === false ? floatval($val) : intval($val);
		}
		return [$val, $convert];
	}

	/**
	 * Find and decide IF/ELSE
	 * @param int|bool $state
	 * @param string $val
	 * @return string
	 */
	protected function decideIfElse($state, string $val): string
	{
		$pos = stripos($val, '{else}');
		if ($pos) {
			if ($state) return substr($val, 0, $pos);
			return substr($val, $pos + 6);
		}
		if ($state) return $val;
		return '';
	}

	/**
	 * Compare values by given operator, return null for undefined operator
	 * @param mixed $x
	 * @param string $operator
	 * @param mixed $y
	 * @return bool|null
	 */
	protected function operator($x, string $operator, $y): ?bool
	{
		switch (str_replace(['&lt;', '&gt;'], ['<', '>'], $operator)) {
			case '==': return $x == $y;
			case '===': return $x === $y;
			case '<>':
			case '!=': return $x != $y;
			case '!==': return $x !== $y;
			case '>': return $x > $y;
			case '<': return $x < $y;
			case '<=': return $x <= $y;
			case '>=': return $x >= $y;
		}
		return null;
	}

	/**
	 * Remove regex from text
	 * @param string $text
	 * @param string $regex
	 * @return string
	 */
	protected function removeRegex(string $text, string $regex): string
	{
		$out = preg_replace('/'.preg_quote($regex, '/').'/', '', $text, 1);
		return $out === null ? '' : trim($out);
	}
}
