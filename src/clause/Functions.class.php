<?php
/**
 * Functions
 *
 * <insert description here>
 *
 * @author Nelson Monterroso <nelson@wikia-inc.com>
 */

namespace FluentSql;

class Functions implements ClauseBuild {
	const MAX = "MAX";
	const MIN = "MIN";
	const COUNT = "COUNT";
	const SUM = "SUM";
	const AVG = "AVG";
	const LOWER = "LOWER";
	const UPPER = "UPPER";

	protected $function;
	protected $functionFields;
	protected $functionAs;


	public function __construct(/* function, ...fields... */ ) {
		$args = func_get_args();
		$this->function = array_shift($args);
		$this->functionFields = $args;
	}

	public function build(Breakdown $bk, $tabs) {
		$fieldFunctionOpenedParenthesis = false;

		if ($this->function != null) {
			$bk->append(" ". $this->function);
			$bk->append(" (");
			$fieldFunctionOpenedParenthesis = true;
		}

		$doCommaField = false;
		foreach ($this->functionFields as $field) {
			if ($doCommaField) {
				$bk->append(",");
			} else {
				$doCommaField = true;
			}
			$field->build($bk, $tabs);
		}

		if ($fieldFunctionOpenedParenthesis) {
			$bk->append(" )");
			$fieldFunctionOpenedParenthesis = false;
		}

		if ($this->functionAs != null) {
			$bk->append(" AS ".$this->functionAs);
		}
	}

	public function functionAs($functionAs=null) {
		if ($functionAs !== null) {
			$this->functionAs = $functionAs;
		}

		return $this->functionAs;
	}
}