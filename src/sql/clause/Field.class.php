<?php
/**
 * Field
 *
 * <insert description here>
 *
 * @author Nelson Monterroso <nelson@wikia-inc.com>
 */

namespace FluentSql;

class Field implements ClauseBuild {
	protected $column;

	/** @var SQL */
	protected $fieldSql;
	protected $function;
	protected $columnAs;
	protected $values;
	protected $condition; //Condition can also be in field

	public function __construct( /*...*/ ) {
		$argc = func_num_args();
		$argv = func_get_args();

		$this->values = [];
		if ($argc > 1) {
			foreach ($argv as $val) {
				if (!($val instanceof Values)) {
					$val = new Values($val);
				}

				$this->values []= $val;
			}
		} elseif ($argc == 1) {
			$arg = $argv[0];
			if ($arg instanceof Values) {
				$this->values []= $arg;
			} elseif ($arg instanceof Functions) {
				$this->function = $arg;
			} elseif ($arg instanceof Condition) {
				$this->condition = $arg;
			} elseif ($arg instanceof SQL) {
				$this->fieldSql = $arg;
			} else { // is_string
				$this->column = $arg;
			}
		}
	}

	public function build(Breakdown $bk, $tabs) {
		if ($this->function != null) {
			$this->function->build($bk, $tabs);
		}

		if ($this->column !== null) {
			$bk->append(" ".$this->column);
		}

		if (count($this->values) > 1) {
			$bk->append(" (");
		}

		$doCommaValues = false;
		foreach ($this->values as $val) {
			/** @var Values $val */
			if ($doCommaValues) {
				$bk->append(",");
			} else {
				$doCommaValues = true;
			}
			$val->build($bk, $tabs);
		}

		if (count($this->values) > 1) {
			$bk->append(" )");
		}

		if ($this->fieldSql != null) {
			if ($this->fieldSql->hasType()) {//Don't put parenthesis when it is not a complex query like SELECT,INSERT.
				$bk->append(" (");//Non complex queries are just VALUES.. FUNCTIONS
			}

			$this->fieldSql->build($bk, $tabs);

			if ($this->fieldSql->hasType()) {
				$bk->append(" )");
			}
		}

		if ($this->condition != null) {
			$this->condition->build($bk, $tabs);
		}

		if ($this->columnAs != null) {
			$bk->append(" AS");
			$bk->append(" ".$this->columnAs);
		}
	}

	public function columnAs($value=null) {
		if ($value !== null) {
			$this->columnAs = $value;
		}

		return $this->columnAs;
	}

	public function numValues() {
		return count($this->values);
	}
}