<?php
interface ClauseBuild {
	function build(Breakdown $bk, $tabs);
}

class Type implements ClauseBuild {
	private static $types = [Type::SELECT, Type::INSERT, Type::UPDATE, Type::DELETE];

	const SELECT = "SELECT";
	const INSERT = "INSERT";
	const UPDATE = "UPDATE";
	const DELETE = "DELETE";

	protected $type;

	// TODO: throw something if the type is invalid?
	public function __construct($type) {
		if (!in_array($type, self::$types)) {
			throw new InvalidArgumentException;
		}

		$this->type = $type;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->tabs($tabs);
		$bk->append(" ".$this->type);
	}

	public function type() {
		return $this->type;
	}
}

class With implements ClauseBuild {
	protected $name;
	protected $recursive;
	protected $sql;

	public function __construct($name, SQL $sql, $recursive) {
		$this->name = $name;
		$this->sql = $sql;
		$this->recursive = $recursive;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" ".$this->name);
		$bk->append(" AS ");
		if($this->sql != null){
			$bk->append(" (");
			$bk->append(Clause::line($tabs));
			$bk->tabs($tabs + 1);
			$this->sql->build($bk, $tabs);
			$bk->append(Clause::line($tabs));
			$bk->append(" )");
			$bk->append(Clause::line(0));
		}
	}

	public function recursive() {
		return $this->recursive;
	}
}

// Strict "Java" style is a lot more verbose, really
class Distinct implements ClauseBuild {
	protected $sql;

	public function __construct($sql){
		$this->sql = $sql;
	}

	public function build(Breakdown $bk, $tabs) {
		if ($this->sql instanceof SQL) {
			$this->sql->build($bk, $tabs);
		} else {
			$bk->append(" ".$this->sql);
		}
	}
}

class DistinctOn extends Distinct {
	// java DistinctOn code is equivalent to java Distinct code
}

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
				$this->values []= new Values($val);
			}
		} else {
			$arg = $argv[0];
			if ($arg instanceof Functions) {
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
}

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

class Set implements ClauseBuild {
	protected $column;
	protected $value;

	public function __construct($column, $value = null) {
		$this->column = $column;
		$this->value = $value;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" " . $this->column);
		$bk->append(" =");
		$bk->append(" ?");
		$bk->addParameter($this->value);
	}

}

class From implements ClauseBuild {
	protected $table;

	public function __construct($table) {
		$this->table = $table;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" FROM");
		$bk->append(" ".$this->table);
	}
}

class Into implements ClauseBuild {
	protected $table;

	public function __construct($table) {
		$this->table = $table;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(Clause::line($tabs+1));
		$bk->append(" INTO");
		$bk->append(" ".$this->table);
	}
}

class Update implements ClauseBuild {
	protected $table;

	public function __construct($table) {
		$this->table = $table;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" ".$this->table);
	}
}

class Values implements ClauseBuild {
	protected $sql;

	public function __construct($sql) {
		$this->sql = $sql;
	}

	public function build(Breakdown $bk, $tabs) {
		if ($this->sql instanceof SQL) {
			$bk->append(" (");
			$this->sql->build($bk, $tabs);
			$bk->append(" )");
		} else {
			$bk->append(" ?");
			$bk->addParameter($this->sql);
		}
	}
}

class Join implements ClauseBuild {
	const INNER_JOIN = "INNER JOIN";
	const LEFT_JOIN = "LEFT JOIN";
	const LEFT_OUTER_JOIN = "LEFT OUTER JOIN";
	const RIGHT_JOIN = "RIGHT JOIN";
	const RIGHT_OUTER_JOIN = "RIGHT OUTER JOIN";
	const CROSS_JOIN = "CROSS JOIN";

	protected $joinType;
	protected $table;
	protected $on;
	protected $using;

	public function __construct($joinType, $table) {
		$this->joinType = $joinType;
		$this->table = $table;
		$this->on = $this->using = [];
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" ".$this->joinType);
		$bk->append(" ".$this->table);
		$this->buildUsing($bk, $tabs);
		$this->buildOn($bk, $tabs);
	}

	public function buildOn(Breakdown $bk, $tabs) {
		$doOnJoinClause = true;
		foreach ($this->on as $on) {
			$bk->append(Clause::line($tabs+2));
			if ($doOnJoinClause) {
				$bk->append(" ON");
				$doOnJoinClause = false;
			} else {
				$bk->append(" AND");
			}
			$on->build($bk, $tabs);
		}
	}

	public function buildUsing(Breakdown $bk, $tabs) {
		$doUsingClause = true;
		$doCommaUsing = false;
		foreach ($this->using as $using) {
			/** @var Using $using */
			if ($doUsingClause) {
				$bk->append(Clause::line($tabs+2));
				$bk->append(" USING");
				$doUsingClause = false;
			}
			if ($doCommaUsing) {
				$bk->append(",");
			} else {
				$doCommaUsing = true;
			}
			$using->build($bk, $tabs);
		}
	}

	public function addOn($on) {
		$this->on []= $on;
	}

	public function addUsing($using) {
		$this->using []= $using;
	}
}

class On implements ClauseBuild {
	protected $column1;
	protected $column2;

	public function __construct($col1, $col2 = null) {
		$this->column1 = $col1;
		$this->column2 = $col2;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->line($tabs+2);
		$bk->append(" ".$this->column1);

		if ($this->column2) {
			$bk->append(" = ");
			$bk->append(" ".$this->column2);
		}
	}

}

class Using implements ClauseBuild {
	protected $column;

	public function __construct($column) {
		$this->column = $column;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" ".$this->column);
	}
}

class Union implements ClauseBuild {
	protected $all;
	protected $sql;

	public function __construct($all, $sql) {
		$this->all = $all;
		$this->sql = $sql;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" UNION");
		if ($this->all) {
			$bk->append(" ALL");
		}
		$this->sql->build($bk, $tabs);
	}
}

class Intersect implements ClauseBuild {
	protected $all;
	protected $sql;

	public function __construct($all, $sql) {
		$this->all = $all;
		$this->sql = $sql;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" INTERSECT");
		if ($this->all) {
			$bk->append(" ALL");
		}
		$this->sql->build($bk, $tabs);
	}
}


class Except implements ClauseBuild {
	protected $all;
	protected $sql;

	public function __construct($all, $sql) {
		$this->all = $all;
		$this->sql = $sql;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" EXCEPT");
		if ($this->all) {
			$bk->append(" ALL");
		}
		$this->sql->build($bk, $tabs);
	}
}

class Limit implements ClauseBuild {
	protected $limit;

	public function __construct($limit) {
		$this->limit = $limit;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" LIMIT");
		$bk->append(" ".$this->limit);
	}
}

class Offset implements ClauseBuild {
	protected $offset;

	public function __construct($offset) {
		$this->offset = $offset;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" OFFSET");
		$bk->append(" ".$this->offset);
	}
}

class Condition implements ClauseBuild {
	const LESS_THAN = "<";
	const LESS_THAN_OR_EQUAL = "<=";
	const EQUAL = "=";
	const GREATER_THAN = ">";
	const GREATER_THAN_OR_EQUAL = ">=";
	const NOT_EQUAL = "!=";
	const IN = "IN";
	const NOT_IN = "NOT IN";
	const EXISTS = "EXISTS";
	const NOT_EXISTS = "NOT EXISTS";

	const LIKE = "LIKE";
	const NULL = "NULL";
	const IS_NULL = "IS NULL";
	const IS_NOT_NULL = "IS NOT NULL";

	const AND_ = "AND";
	const OR_ = "OR";

	protected $connector;
	protected $left;
	protected $right;
	protected $equality;

	public function __construct($left=null, $operator=null, $right=null) {
		if ($operator !== null) {
			$this->equality = $operator;
		}

		if ($left instanceof Field || $left instanceof Functions) {
			$this->left = $left;
		} elseif ($left !== null) {
			$this->left = new Field($left);
		}

		if ($right instanceof Field || $right instanceof Functions) {
			$this->right = $right;
		} elseif ($right !== null) {
			$this->right = new Field(new Values($right));
		}
	}

	public function build(Breakdown $bk, $tabs) {
		if ($this->left != null) {
			$this->left->build($bk, $tabs);
		}

		$bk->append(" ".$this->equality);

		if ($this->right != null) {
			$this->right->build($bk, $tabs);
		}
	}

	public function connector($type=null) {
		if ($type !== null) {
			$this->connector = $type;
		}

		return $this->connector;
	}

	public function left($left=null) {
		if ($left !== null) {
			$this->left = $left;
		}

		return $this->left;
	}

	public function equality($equality=null) {
		if ($equality !== null) {
			$this->equality = $equality;
		}

		return $this->equality;
	}

	public function right($right=null) {
		if ($right !== null) {
			$this->right = $right;
		}

		return $this->right;
	}
}

class Where implements ClauseBuild {
	protected $conditions;

	public function __construct() {
		$this->conditions = [];
	}

	public function add(Condition $condition) {
		$this->conditions []= $condition;
	}

	public function and_($condition) {
		if (!($condition instanceof Condition)) {
			$condition = new Condition($condition);
		}

		$condition->connector(Condition::AND_);
		$this->add($condition);
	}

	public function or_($condition) {
		if (!($condition instanceof Condition)) {
			$condition = new Condition($condition);
		}

		$condition->connector(Condition::OR_);
		$this->add($condition);
	}

	public function build(Breakdown $bk, $tabs) {
		$doWhere = true;
		/** @var Condition $condition */
		foreach ($this->conditions as $condition) {
			if ($doWhere) {
				$bk->line($tabs + 1);
				$bk->append(" WHERE");
				$doWhere = false;
			} else {
				$bk->line($tabs + 1);
				$bk->append(" ".$condition->connector());
			}

			$condition->build($bk, $tabs);
		}
	}

	public function conditions($condition=null) {
		if ($condition != null) {
			$this->conditions []= $condition;
		}

		return $this->conditions;
	}
}

class In implements ClauseBuild {
	/**
	 * @var SQL|array
	 */
	protected $values;

	/**
	 * @var bool
	 */
	protected $in;

	public function __construct($values, $in=true) {
		$this->values = $values;
		$this->in = $in;
	}

	public function build(Breakdown $bk, $tabs) {
		if (!$this->in) {
			$bk->append(" NOT");
		}

		$bk->append(" IN");
		$bk->append(" (");

		if ($this->values instanceof SQL) {
			$this->values->build($bk, $tabs);
		} else {
			$bk->append(explode(',', $this->values));
		}

		$bk->append(" )");
	}
}

class OrderBy implements ClauseBuild {
	protected $column;
	protected $asc;

	public function __construct($column, $asc=true) {
		$this->column = $column;
		$this->asc = $asc;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->append(" ".$this->column);

		if (!$this->asc) {
			$bk->append(" DESC");
		}
	}

	public function asc($asc=null) {
		if ($asc !== null) {
			$this->asc = $asc;
		}

		return $this->asc;
	}
}

class GroupBy implements ClauseBuild {
	protected $sql;

	public function __construct($sql) {
		$this->sql = $sql;
	}

	public function build(Breakdown $bk, $tabs) {
		if ($this->sql instanceof SQL) {
			(new Field($this->sql))->build($bk, $tabs);
		} else {
			$bk->append(" ".$this->sql);
		}
	}
}

class Having implements ClauseBuild {
	protected $conditions;

	public function __construct(Condition $condition) {
		$this->conditions = [$condition];
	}

	public function build(Breakdown $bk, $tabs) {
		$doHaving = true;
		/** @var Condition $condition */
		foreach ($this->conditions as $condition) {
			if ($doHaving) {
				$bk->line($tabs);
				$bk->append(" HAVING");
				$doHaving = false;
			}

			$condition->build($bk, $tabs);
		}
	}

	public function conditions($condition=null) {
		if ($condition != null) {
			$this->conditions []= $condition;
		}

		return $this->conditions;
	}
}

/*
unimplemented
class Window implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}

class Returning implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}

class Cases implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}
*/

class Clause {
	public static function line($tabs) {
		return "\n".Clause::tabs($tabs);
	}

	public static function tabs($tabs) {
		$tstr = "";
		for($i = 0; $i < $tabs; $i++){
			$tstr .= "\t";
		}
		return $tstr;
	}

}
