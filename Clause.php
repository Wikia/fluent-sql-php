<?php

interface ClauseBuild{
	function build(Breakdown $bk, $tabs);
}

class Type implements ClauseBuild {
	public $type;
	var $types = [Type::SELECT, Type::INSERT, Type::UPDATE, Type::DELETE];
	const SELECT = "SELECT";
	const INSERT = "INSERT";
	const UPDATE = "UPDATE";
	const DELETE = "DELETE";

	// TODO: throw something if the type is invalid?
	public function __construct($type) {
		$this->type = $type;
	}

	public function build(Breakdown $bk, $tabs) {
		if (in_array($this->type, $this->types)) {
			$bk->append(Clause::tabs($tabs));
			$bk->append(" " . $this->type);
		}
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

	public function build (Breakdown $bk, $tabs) {
		$bk->append(" ".$name);
		$bk->append(" AS ");
		if($this->sql != null){
			$bk->append(" (");
			$bk->append(Clause::line($tabs));
			$bk->append(Clause::tabs($tabs+1));
			$this->sql->build($bk, $tabs);
			$bk->append(Clause::line(tabs));
			$bk->append(" )");
			$bk->append(Clause::line(0));
		}
	}
}

// Strict "Java" style is a lot more verbose, really
class Distinct implements ClauseBuild {

	protected $column;
	protected $sql;

	public function __construct($arg){
		if ($arg instanceof SQL) {
			$this->sql = $arg;
		} else {
			$this->column = $arg;
		}
	}

	public function build (Breakdown $bk, $tabs) {
		if($this->column != null){
			$bk->append(" "+$this->column);
		}
		if($this->sql != null){
			$this->sql->build($bk, $tabs);
		}
	}

}

class DistinctOn implements ClauseBuild {

	protected $column;
	protected $sql;

	public function __construct($arg){
		if ($arg instanceof SQL) {
			$this->sql = $arg;
		} else {
			$this->column = $arg;
		}
	}

	public function build (Breakdown $bk, $tabs) {
		if($this->column != null){
			$bk->append(" "+$this->column);
		}
		if($this->sql != null){
			$this->sql->build($bk, $tabs);
		}
	}
}


/* // TODO: rewrite in more idiomatic "PHP" style

class Distinct implements ClauseBuild {
	protected $sql;

	public function __construct ($sql) {
		$this->sql = $sql;
	}

	public function build (Breakdown $bk, $tabs) {
		if ( $this->sql instanceof SQL ) {
			$this->sql->build($bk, $tabs);
		} else {
			$bk->append(" "+$this->sql);
		}
	}
}
*/

class Field implements ClauseBuild {
		protected $column;
		protected $fieldSql;
		protected $function;
		protected $columnAs;
		protected $values = [];
		protected $condition; //Condition can also be in field

		public function __construct( /*...*/ ) {
			$argc = func_num_args();
			$argv = func_get_args();
			if ($argc > 1) {
				foreach ($argv as $val) {
					$this->values[] = new Values($val);
				}
			} else {
				$arg = $argv[0];
				if ($arg instanceof Functions) {
					$this->function = $arg;
				} elseif ($arg instanceof Condition) {
					$this->condition = $arg;
				} elseif ($arg instanceof SQL) {
					$this->fieldSQL = $arg;
				} else { // is_string
					$this->column = $arg;
				}
			}
		}

	public function build (Breakdown $bk, $tabs) {

		if($this->function != null){
			$this->function->build($bk, $tabs);
		}
		if($this->column != null){
			$bk.append(" "+$this->column);
		}
		$doCommaValues = false;

		if(count($this->values) > 1) {
			bk.append(" (");
		}
		foreach ($this->values as $val) {
			if ($doCommaValues) {
				$bk->append(",");
			} else {
				$doCommaValues = true;
			}
			$val->build($bk, $tabs);
		}
		if(count($this->values) > 1) {
			bk.append(" )");
		}

		if($this->fieldSQL != null) {
			if($this->fieldSql->type != null){//Don't put parenthesis when it is not a complex query like SELECT,INSERT.
				$bk->append(" (");//Non complex queries are just VALUES.. FUNCTIONS
			}
			$fieldSql->build($bk, $tabs);
			if($this->fieldSql->type != null){
				$bk->append(" )");
			}
		}
		if($this->condition != null){
			$this->condition->build($bk, $tabs);
		}
		if($this->columnAs != null){
			$bk.append(" AS");
			$bk.append(" ".$this->columnAs);
		}
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
	protected $functionFields = [];
	protected $functionAs;


	public function __construct(/* function, ...fields... */ ) {
		$args = func_get_args();
		$this->function = array_pop($args);
		// TODO: just array_merge this
		foreach ($args as $field) {
			$this->functionFields[] = $field;
		}
	}

	public function build (Breakdown $bk, $tabs) {
		$fieldFunctionOpenedParenthesis = false;
		if ($this->function != null) {
			$bk->append(" ". $this->function);
			$bk->append(" (");
			$fieldFunctionOpenedParenthesis = true;
		}
		$doCommaField = false;
		foreach ($functionFields as $field) {
			if($doCommaField) {
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
			$bk->append(" AS " . $this->functionAs);
		}
	}

}

class Set implements ClauseBuild {

	protected $column;
	protected $value;

	public function __construct($column, $value = null) {
		$this->column = $column;
		$this->value = $value;
	}

	public function build (Breakdown $bk, $tabs) {
		$bk->append(" " . $this->column);
		$bk->append(" =");
		$bk->append(" ?");
		$bk->addParameter($this->value);
	}

}

/* not implemented
class Cases implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}
*/

class From implements ClauseBuild {

	protected $table;

	public function __construct($table) {
		$this->table = $table;
	}

	public function build (Breakdown $bk, $tabs) {
		$bk->append(" FROM");
		$bk->append(" " . $this->table);
	}

}

class Into implements ClauseBuild {

	protected $table;

	public function __construct($table) {
		$this->table = $table;
	}

	public function build (Breakdown $bk, $tabs) {
		$bk->append(Clause::line($tabs+1));
		$bk->append(" INTO");
		$bk->append(" " . $this->table);

	}

}

class Update implements ClauseBuild {

	protected $table;

	public function __construct($table) {
		$this->table = $table;
	}

	public function build (Breakdown $bk, $tabs) {
		$bk->append(" " . $this->table);
	}
}

class Values implements ClauseBuild {

	protected $singleValue;
	protected $sql;

	public function __construct($sql) {
		if ($sql instanceof SQL) {
			$this->sql = $sql;
		} else {
			$this->singleValue = $sql;
		}
	}

	public function build (Breakdown $bk, $tabs) {
		if ($this->singleValue != null) {
			$bk->append(" ?");
			$bk->addParameter($this->singleValue);
		}
		if ($this->sql != null) {
			$bk->append(" (");
			$this->sql->build($bk, $tabs);
			$bk->append(" )");
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
	protected $on = [];
	protected $using  = [];

	public function __construct($joinType, $table) {
		$this->joinType = $joinType;
		$this->table = $table;
	}

	public function build (Breakdown $bk, $tabs) {
		$bk->append(" " . $this->joinType);
		$bk->append(" " . $this->table);
		$this->buildUsing($bk, $tabs);
		$this->buildOn($bk,$tabs);
	}

	public function buildOn(Breakdown $bk, $tabs) {
		$doOnJoinClause = true;
		foreach ($this->on as $on) {
			$bk->append(Clause::line($tabs+2));
			if($doOnJoinClause) {
				$bk.append(" ON");
				$doOnJoinClause = false;
			} else {
				$bk.append(" AND");
			}
			$on->build($bk, $tabs);
		}
	}

	public function buildUsing(Breakdown $bk, $tabs) {
		$doUsingClause = true;
		$doCommaUsing = false;
		foreach ($this->using as $using) {
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
}

class On implements ClauseBuild {
	protected $column1;
	protected $column2;

	public function __construct($col1, $col2 = null) {
		$this->column1 = $col1;
		$this->column2 = $col2;
	}

	public function build (Breakdown $bk, $tabs) {
		$bk->line($tabs+2);
		$bk->append(" "+$this->column1);
		if ($this->column2) {
			$bk->append(" = ");
			$bk->append(" "+$this->column2);
		}
	}

}

class Using implements ClauseBuild {
	protected $column;

	public function __constrcut($column) {
		$this->column = $column;
	}

	public function build (Breakdown $bk, $tabs) {
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

	public function build (Breakdown $bk, $tabs) {
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

	public function build (Breakdown $bk, $tabs) {
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

	public function build (Breakdown $bk, $tabs) {
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

	public function build (Breakdown $bk, $tabs) {
		$bk->append(" LIMIT");
		$bk->append(" " . $this->limit);
	}

}

class Offset implements ClauseBuild {

	protected $offset;

	public function __construct($offset) {
		$this->offset = $offset;
	}

	public function build (Breakdown $bk, $tabs) {
		$bk->append(" OFFSET");
		$bk->append(" " . $this->offset);
	}

}

class Condition implements ClauseBuild {

	protected $connector;
	protected $left;
	protected $right;
	protected $equality;

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

	public function __construct($left, $operator, $right) {
		$this->equality = $operator;

		if ($left instanceof Field || $left instanceof Functions) {
			$this->left = $left;
		} else {
			$this->left = new Field($left);
		}

		if ($right instanceof Field || $right instanceof Functions) {
			$this->right = $right;
		} else {
			$this->right = new Field(new Values($right));
		}
	}

	public function build (Breakdown $bk, $tabs) {
		if ($this->left != null) {
			$this->left->build($bk, $tabs);
		}
		$bk->append(" " + $this->equality);
		if ($this->right != null) {
			$this->right->build($bk, $tabs);
		}
	}

}

class Where implements ClauseBuild {

	protected $conditions = [];

	public function add($condition) {
		$this->conditions []= $condition;
	}

	/*
	public function where_and($condition)

		public void and(String $column1){
			Condition condition = new Condition();
			condition.connector = Condition.AND;
			condition.field1 = new Field(column1);
			add(condition);
		}

		public void and(Condition $condition){
			condition.connector = Condition.AND;
			add(condition);
		}
		public void or(Condition $condition){
			condition.connector = Condition.OR;
			add(condition);
		}
	*/

	public function build (Breakdown $bk, $tabs) {

	}

}

class In implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}

class OrderBy implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}

class GroupBy implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}

class Window implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}

class Returning implements ClauseBuild {

	public function build (Breakdown $bk, $tabs) {

	}

}

class Clause {

	public static function line($tabs) {
		return "\n"+Clause::tabs($tabs);
	}

	public static function tabs($tabs) {
		$tstr = "";
		for($i = 0; $i < $tabs; $i++){
			$tstr .= "\t";
		}
		return $tstr;
	}

}
