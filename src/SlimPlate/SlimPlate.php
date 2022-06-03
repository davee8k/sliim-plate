<?php
namespace SlimPlate;

/**
 * Bare bone templating system for text strings, based on Latte syntax
 * Self-fixing >< conversion by HTML editors (TinyMCE)
 * Support $VARIALBLES, "VALUES", IF with one condition, ELSE and
 * That's all Folks!
 *
 * {if $a->b == 5}OK{else}NO{/if}
 *
 * @version 0.85302
 * @author DaVee
 * @license http://www.wtfpl.net/
 */
class SlimPlate {
	/** @var string */
	protected static $regexIf = '/\{if((?:(?!\{if).)*)\}((?:(?!\{if).)+)\{\/if\}/iU';
	/** @var string */
	protected $template = '';
	/** @var array */
	protected $matchData = null;

	/**
	 * SlimPlate constructor.
	 * @param string $template
	 */
	public function __construct ($template) {
		$this->template = $template;
	}

	/**
	 * Check template for errors, throw \InvalidArgumentException
	 * @param array $data
	 * @param bool $strict
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function check (array $data = array(), $strict = false) {
		$text = $this->template;
		while (preg_match_all(self::$regexIf, $text, $matches)) {
			if (!empty($matches[0])) {
				foreach ($matches[0] as $i=>$match) {
					$val = '';
					$con = trim($matches[1][$i]);
					$text = preg_replace('/'.preg_quote($match,'/').'/', '', $text, 1);

					if (preg_match_all('/{else}/i', $matches[2][$i]) > 1) throw new \InvalidArgumentException("Multiple else in: ".$matches[2][$i]);

					if ($con !== '') {
						if ($con[0] === '$') $val = $this->findVar($con, $data);
						else $val = $this->findValue($con);
					}
					if ($val) $con = trim(preg_replace('/'.preg_quote($val[0],'/').'/', '', $con, 1));
					else throw new \InvalidArgumentException('Empty IF condition');

					if ($con) {
						$operator = $this->findOperator($con);
						if ($operator && $this->operator(0, $operator[0], 0) !== null) $con = trim(preg_replace('/'.preg_quote($operator[0],'/').'/', '', $con, 1));
						else throw new \InvalidArgumentException("Undefined IF operator: ".$con);

						$val = '';
						if ($con !== '') {
							if ($con[0] === '$') $val = $this->findVar($con, $data);
							else $val = $this->findValue($con);
						}
						if ($val) $con = trim(preg_replace('/'.preg_quote($val[0],'/').'/', '', $con, 1));
						else throw new \InvalidArgumentException('Missing second variable: '.trim($matches[1][$i]));
					}
				}
			}
		}

		if (!empty($data)) {
			$notFound = $data;
			foreach ($this->matchVariables() as $match) {
				if (count($match) === 2 && isset($data[$match[1]])) {
					if (isset($notFound[$match[1]])) unset($notFound[$match[1]]);
				}
				else if (count($match) === 3 && isset($data[$match[1]][$match[2]])) {
					if (isset($notFound[$match[1]][$match[2]])) {
						unset($notFound[$match[1]][$match[2]]);
						if (empty($notFound[$match[1]])) unset($notFound[$match[1]]);
					}
				}
				else throw new \InvalidArgumentException("Unset variable: ".$match[0]);
			}
			if ($strict && !empty($notFound)) throw new \InvalidArgumentException("Unused variables: ".implode(',', array_keys($notFound)));
		}
		return true;
	}

	/**
	 * Render template with current $data
	 * @param array $data
	 * @return String
	 */
	public function render (array $data = array()) {
		$text = $this->template;
		while (preg_match_all(self::$regexIf, $text, $matches)) {
			if (!empty($matches[0])) {
				foreach ($matches[0] as $i=>$match) {
					$con = trim($matches[1][$i]);
					$firstVal = null;
					$secVal = null;
					try {
						if ($con[0] === '$') $firstVal = $this->findVar($con, $data);
						else $firstVal = $this->findValue($con);
						if ($firstVal) $con = trim(preg_replace('/'.preg_quote($firstVal[0],'/').'/', '', $con, 1));

						if ($con) {
							$operator = $this->findOperator($con);
							if ($operator) {
								$con = trim(preg_replace('/'.preg_quote($operator[0],'/').'/', '', $con, 1));

								if ($con[0] === '$') $secVal = $this->findVar($con, $data);
								else $secVal = $this->findValue($con);
								if ($secVal) $con = trim(preg_replace('/'.preg_quote($secVal[0],'/').'/', '', $con, 1));

								$show = $this->operator($firstVal[1], $operator[0], $secVal[1]);
								if (!$con && $show !== null) $text = preg_replace('/'.preg_quote($match,'/').'/', $this->decideIfElse($show, $matches[2][$i]), $text, 1);
							}
						}
						else if (!$con)	$text = preg_replace('/'.preg_quote($match,'/').'/', $this->decideIfElse($firstVal[1], $matches[2][$i]), $text, 1);
					}
					catch (\InvalidArgumentException $e) {
						// ignore errors
					}
					$text = preg_replace('/'.preg_quote($match,'/').'/', '&#'.ord('{').';'.substr($match, 1, -1).'&#'.ord('}').';', $text, 1);
				}
			}
		}

		if ($data) {
			foreach ($this->matchVariables() as $match) {
				if (count($match) === 2 && isset($data[$match[1]])) {
					$text = preg_replace('/'.preg_quote($match[0]).'/', $data[$match[1]], $text, 1);
				}
				else if (count($match) === 3 && isset($data[$match[1]][$match[2]])) {
					$text = preg_replace('/'.preg_quote($match[0]).'/', $data[$match[1]][$match[2]], $text, 1);
				}
			}
		}
		return $text;
	}

	/**
	 * Find all variables {$var} in template
	 * @return array
	 */
	protected function matchVariables () {
		if ($this->matchData === null) preg_match_all('/\{\$([a-z][a-z\d_]*)(?:(?:->|-&gt;)([a-z][a-z\d_]*))?\}/iU', $this->template, $this->matchData, PREG_SET_ORDER);
		return $this->matchData;
	}

	/**
	 * Get variable name from condition a its value
	 * @param string $val
	 * @param array $data
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	protected function findVar ($val, $data) {
		preg_match('/^\$([a-z][a-z\d_]*)(?:(?:->|-&gt;)([a-z][a-z\d_]*))?/i', $val, $vars);

		if (empty($vars)) throw new \InvalidArgumentException("Wrong variable definition");
		else {
			if ($data) {
				if (count($vars) === 2 && isset($data[$vars[1]])) $vars[1] = $data[$vars[1]];
				else if (count($vars) === 3 && isset($data[$vars[1]][$vars[2]])) $vars[1] = $data[$vars[1]][$vars[2]];
				else throw new \InvalidArgumentException("Undefined variable: ".$vars[0]);
			}
		}
		return $vars;
	}

	/**
	 * Find comparation function
	 * @param string $val
	 * @return array
	 */
	protected function findOperator ($val) {
		preg_match('/^([=<>]|&gt;|&lt;)+/i', $val, $operator);
		return $operator;
	}

	/**
	 * Get value from condition
	 * @param string $val
	 * @return array
	 */
	protected function findValue ($val) {
		if ($val[0] === '"') return $this->readValueCon($val, '"');
		else if ($val[0] === "'") return $this->readValueCon($val, "'");
		else if (substr($val[0], 0, 6) === '&quot;') return $this->readValueCon($val, '&quot;');
		else if (substr($val[0], 0, 6) === '&#039;') return $this->readValueCon($val, '&#039;');
		return $this->readValueCon($val, null);
	}

	/**
	 * Return value
	 * @param string $val
	 * @param string $sep
	 * @return string|array
	 * @throws \InvalidArgumentException
	 */
	protected function readValueCon ($val, $sep) {
		if ($sep !== null) {
			$sep = preg_quote($sep, '/');
			preg_match('/^'.$sep.'(.*)(?!\\\\'.$sep.').'.$sep.'/iU', $val, $matches);
			if ($matches) return $matches;
		}
		else {
			preg_match('/( |=|<|>|&lt;|&gt;)/i', $val, $matches);
			if ($matches) return $this->convertValueCon(substr($val, 0, strpos($val, $matches[0])));
			return $this->convertValueCon($val);
		}
		throw new \InvalidArgumentException("Undefined value: ".$val);
	}

	/**
	 * Convert found value into correct type
	 * @param string $val
	 * @return array	int/float/string
	 */
	protected function convertValueCon ($val) {
		if (is_numeric($val)) {
			if (is_int($val)) $val = intval($val);
			else $val = floatval($val);
		}
		return array($val, $val);
	}

	/**
	 * Find and decide IF/ELSE
	 * @param bool $state
	 * @param string $val
	 * @return string
	 */
	protected function decideIfElse ($state, $val) {
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
	protected function operator ($x, $operator, $y) {
		switch ($operator) {
			case '==': return $x == $y;
			case '===': return $x === $y;
			case '&lt;&gt;':
			case '<>':
			case '!=': return $x != $y;
			case '!==': return $x !== $y;
			case '&gt;':
			case '>': return $x > $y;
			case '&lt;':
			case '<': return $x < $y;
			case '&lt;=':
			case '<=': return $x <= $y;
			case '&gt;=':
			case '>=': return $x >= $y;
		}
		return null;
	}
}