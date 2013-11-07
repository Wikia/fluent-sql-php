<?php
namespace FluentSql;

class SQL {
	protected $callOrder = [];
	protected $withQueries = [];

	/** @var Type */
	protected $type;

	protected $fields = [];
	protected $functions = [];
	protected $cases = [];
	protected $set = [];
	protected $distinctColumns = [];
	protected $distinctOnColumns = [];

	/** @var From */
	protected $from;

	/** @var Update */
	protected $update;

	/** @var Into */
	protected $into;

	/** @var bool */
	protected $doCommaField = false;

	protected $values = [];
	protected $joins = [];
	protected $union = [];
	protected $intersect = [];
	protected $except = [];

	/** @var Where */
	protected $where;

	protected $orderBy = [];
	protected $groupBy = [];

	/** @var Having */
	protected $having;

	protected $returning = [];

	/** @var Limit */
	protected $limit;

	/** @var Offset */
	protected $offset;

	/** @var Cache */
	protected $cacheTtl = 0;

	/** @var Database */
	protected $db;

	private function called($call) {
		$this->callOrder []= $call;

		return $this;
	}

	public function hasType() {
		return $this->type != null;
	}

	public function getType() {
		return $this->type->type();
	}

	public function WITH($name, SQL $sql) {
		$with = new With($name,$sql,false);
		$this->withQueries []= $with;

		return $this->called($with);
	}

	public function WITH_RECURSIVE($name, SQL $sql) {
		$with = new With($name,$sql,true);
		$this->withQueries []= $with;

		return $this->called($with);
	}

	public function SELECT_ALL() {
		return $this->SELECT("*");
	}

	/**
	 * @return SQL
	 */
	public function SELECT() {
		$this->type = new Type(Type::SELECT);
		$this->called($this->type);

		foreach (func_get_args() as $col) {
			$this->FIELD($col);
		}

		return $this;
	}

	public function UPDATE($table) {
		$this->type = new Type(Type::UPDATE);
		$this->called($this->type);
		$this->update = new Update($table);

		return $this->called($this->update);
	}

	public function INSERT() {
		$this->type = new Type(Type::INSERT);

		return $this->called($this->type);
	}

	public function DELETE() {
		$this->type = new Type(Type::DELETE);
		return $this->called($this->type);
	}

	public function DISTINCT() {
		foreach (func_get_args() as $col) {
			$distinct = new Distinct($col);
			$this->distinctColumns []= $distinct;
			$this->called($distinct);
		}

		return $this;
	}

	public function DISTINCT_ON() {
		foreach (func_get_args() as $col) {
			$distinctOn = new DistinctOn($col);
			$this->distinctOnColumns []= $distinctOn;
			$this->called($distinctOn);
		}

		return $this;
	}

	public function FIELD() {
		foreach (func_get_args() as $sql) {
			$field = new Field($sql);
			$this->fields []= $field;
			$this->called($field);
		}

		return $this;
	}

	/**
	 * @param $sql
	 * @return SQL
	 */
	public function SUM($sql) {
		return $this->function_(Functions::SUM, new Field($sql));
	}

	public function COUNT($sql) {
		return $this->function_(Functions::COUNT, new Field($sql));
	}

	public function MAX($sql) {
		return $this->function_(Functions::MAX, new Field($sql));
	}

	public function MIN($sql) {
		return $this->function_(Functions::MIN, new Field($sql));
	}

	public function AVG($sql) {
		return $this->function_(Functions::AVG, new Field($sql));
	}

	public function LOWER($sql) {
		return $this->function_(Functions::LOWER, new Field($sql));
	}

	public function UPPER($sql) {
		return $this->function_(Functions::UPPER, new Field($sql));
	}

	public function AS_($columnAs) {
		$lastCall = $this->getLastCall();

		if($lastCall instanceof Field){
			$lastCall->columnAs($columnAs);
		} elseif($lastCall instanceof Functions){
			$lastCall->functionAs($columnAs);
		}

		return $this;
	}

	public function FROM($table) {
		$this->from = new From($table);

		return $this->called($this->from);
	}

	public function LEFT_JOIN($table) {
		return $this->JOIN($table, Join::LEFT_JOIN);
	}

	public function RIGHT_JOIN($table) {
		return $this->JOIN($table, Join::RIGHT_JOIN);
	}

	public function INNER_JOIN($table) {
		return $this->JOIN($table, Join::INNER_JOIN);
	}

	public function CROSS_JOIN($table) {
		return $this->JOIN($table, Join::CROSS_JOIN);
	}

	public function LEFT_OUTER_JOIN($table) {
		return $this->JOIN($table, Join::LEFT_OUTER_JOIN);
	}

	public function RIGHT_OUTER_JOIN($table) {
		return $this->JOIN($table, Join::RIGHT_OUTER_JOIN);
	}

	public function JOIN($table, $type=Join::INNER_JOIN) {
		$join = new Join($type, $table);
		$this->joins []= $join;

		return $this->called($join);
	}

	public function ON() {
		$args = func_get_args();
		$column1 = $args[0];
		$column2 = isset($args[1]) ? $args[1] : null;

		$on = new On($column1, $column2);
		$join = $this->getLastJoin();

		if ($join === null) {
			// TODO: make a sql exception?
			throw new Exception('using ON without a JOIN');
		}

		$join->addOn($on);

		return $this->called($on);
	}

	/**
	 * if called with column 1 and 2, this is an AND from a JOIN context,
	 * if called with only column 1 this is an AND from a WHERE context
	 *
	 * @param $column1
	 * @param $column2
	 * @return $this
	 */
	public function AND_($column1, $column2=null) {
		if ($column2 !== null) {
			return $this->ON($column1, $column2);
		} else {
			if ($this->where == null) {
				return $this->WHERE($column1);
			}

			$condition = new Condition($column1);
			$this->where->and_($condition);

			return $this->called($condition);
		}
	}

	public function USING(/** args */) {
		$join = $this->getLastJoin();

		foreach (func_get_args() as $column) {
			$using = new Using($column);
			$join->addUsing($using);
			$this->called($using);
		}

		return $this;
	}

	public function UNION(SQL $sql, $all=false) {
		$union = new Union($all, $sql);
		$this->union []= $union;

		return $this->called($union);
	}

	public function UNION_ALL(SQL $sql) {
		return $this->UNION($sql, true);
	}

	public function INTERSECT(SQL $sql, $all=false) {
		$intersect = new Intersect($all, $sql);
		$this->intersect []= $intersect;

		return $this->called($intersect);
	}

	public function INTERSECT_ALL(SQL $sql) {
		return $this->INTERSECT($sql, true);
	}

	public function EXCEPT(SQL $sql, $all=false) {
		$except = new Except($all, $sql);
		$this->except []= $except;

		return $this->called($except);
	}

	public function EXCEPT_ALL(SQL $sql) {
		return $this->EXCEPT($sql, true);
	}

	public function INTO($table) {
		$this->into = new Into($table);

		return $this->called($this->into);
	}

	public function VALUE(/** args */) {
		foreach (func_get_args() as $arg) {
			$value = new Values($arg);
			$this->values []= $value;
			$this->called($value);
		}

		return $this;
	}

	public function VALUES(/** args */) {
		return call_user_func_array([$this, 'VALUE'], func_get_args());
	}

	public function SET($field, $value) {
		$set = new Set($field, $value);
		$this->set []= $set;

		return $this->called($set);
	}

	public function WHERE($column) {
		if ($this->where == null) {
			$this->where = new Where();
		}

		$condition = new Condition($column);
		$this->where->add($condition);

		return $this->called($condition);
	}

	public function OR_($column) {
		if ($this->where == null) {
			return $this->WHERE($column);
		}

		$condition = new Condition($column);
		$this->where->or_($condition);

		return $this->called($condition);
	}

	public function EQUAL_TO($value) {
		return $this->whereOp($value, Condition::EQUAL);
	}

	public function EQUAL_TO_FIELD($value) {
		return $this->whereOp($value, Condition::EQUAL, false);
	}

	public function GREATER_THAN($value) {
		return $this->whereOp($value, Condition::GREATER_THAN);
	}

	public function GREATER_THAN_OR_EQUAL($value) {
		return $this->whereOp($value, Condition::GREATER_THAN_OR_EQUAL);
	}

	public function LESS_THAN($value) {
		return $this->whereOp($value, Condition::LESS_THAN);
	}

	public function LESS_THAN_OR_EQUAL($value) {
		return $this->whereOp($value, Condition::LESS_THAN_OR_EQUAL);
	}

	public function NOT_EQUAL_TO($value) {
		return $this->whereOp($value, Condition::NOT_EQUAL);
	}

	public function NOT_EQUAL_TO_FIELD($value) {
		return $this->whereOp($value, Condition::NOT_EQUAL, false);
	}

	public function IS_NOT_NULL() {
		return $this->whereOp(null, Condition::IS_NOT_NULL);
	}

	public function IS_NULL() {
		return $this->whereOp(null, Condition::IS_NULL);
	}

	public function BETWEEN($value1, $value2) {
		$this->whereOp($value1, Condition::BETWEEN);
		$this->AND_(new Values($value2));
		array_pop($this->callOrder);

		return $this;
	}

	public function IN(/** args */) {
		return $this->inHelper(func_get_args(), Condition::IN);
	}

	public function NOT_IN(/** args */) {
		return $this->inHelper(func_get_args(), Condition::NOT_IN);
	}

	public function EXIST(/** args */) {
		return $this->inHelper(func_get_args(), Condition::EXISTS);
	}

	public function NOT_EXISTS(/** args */) {
		return $this->inHelper(func_get_args(), Condition::NOT_EXISTS);
	}

	public function ORDER_BY(/** args */) {
		foreach (func_get_args() as $field) {
			if (is_array($field)) {
				list($field, $asc) = $field;
			} else {
				$asc = true;
			}

			$orderBy = new OrderBy($field, $asc);
			$this->orderBy []= $orderBy;
			$this->called($orderBy);
		}

		return $this;
	}

	public function DESC() {
		$count = count($this->orderBy);

		if ($count > 0) {
			/** @var OrderBy $lastOrderBy */
			$lastOrderBy = $this->orderBy[$count - 1];
			$lastOrderBy->asc(false);

			return $this->called($lastOrderBy);
		}

		return null;
	}

	/**
	 * @return SQL
	 */
	public function GROUP_BY(/** args */) {
		foreach (func_get_args() as $arg) {
			$group = new GroupBy($arg);
			$this->groupBy []= $group;
			$this->called($group);
		}

		return $this;
	}

	public function HAVING($column) {
		$condition = new Condition($column);
		$having = new Having($condition);
		$this->having = $having;

		return $this->called($having);
	}

	public function LIMIT($limit) {
		$this->limit = new Limit($limit);

		return $this->called($this->limit);
	}

	public function OFFSET($offset) {
		$this->offset = new Offset($offset);

		return $this->called($offset);
	}

	public function cache($ttl) {
		$this->cacheTtl = $ttl;

		return $this;
	}

	public function run($db, callable $callback, $key=null) {
		$breakDown = $this->build();
		$cache = $this->getCache();
		$result = null;

		if ($this->cacheEnabled()) {
			$result = $cache->get($breakDown, $key);
		}

		if ($result === false) {
			$result = $this->query($db, $breakDown, $callback);

			if ($this->cacheEnabled()) {
				$cache->set($breakDown, $result, $this->cacheTtl, $key);
			}
		}

		return $result;
	}

	public function build($bk=null, $tabs=0) {
		if ($bk === null) {
			$bk = new Breakdown();
		}

		$this->doCommaField = false;

		$this->buildClauseAllCTEs($bk, $tabs);
		$this->buildClauseType($bk, $tabs);
		$this->buildClauseAllDistinctOnColumns($bk, $tabs);
		$this->buildClauseAllDistinctColumns($bk, $tabs);
		$this->buildClauseAllFunctions($bk, $tabs);
		$this->buildClauseAllFields($bk, $tabs);
		$this->buildClauseInto($bk, $tabs);
		$this->buildClauseUpdate($bk, $tabs);
		$this->buildClauseAllSet($bk, $tabs);
		$this->buildClauseFrom($bk, $tabs);
		$this->buildClauseAllJoin($bk, $tabs);
		$this->buildClauseWhere($bk, $tabs);
		$this->buildClauseAllUnion($bk, $tabs);//TODO: find a resolution to whether where or union comes first
		$this->buildClauseAllIntersect($bk, $tabs);
		$this->buildClauseAllExcept($bk, $tabs);
		$this->buildClauseAllGroupBy($bk, $tabs);
		$this->buildClauseAllHaving($bk, $tabs);
		$this->buildClauseAllValues($bk, $tabs);
		$this->buildClauseAllOrderBy($bk, $tabs);
		$this->buildClauseLimit($bk, $tabs);
		$this->buildClauseOffset($bk, $tabs);

		return $bk;
	}

	private function buildClauseAllCTEs(Breakdown $bk, $tabs) {
		$doComma = false;
		foreach ($this->withQueries as $with) {
			/** @var With $with */
			$bk->append(Clause::line($tabs));

			if ($doComma) {
				$bk->append(',');
			} else {
				$bk->append(' WITH');
				$doComma = true;
			}

			if ($with->recursive()) {
				$bk->append(' RECURSIVE');
			}

			$with->build($bk, $tabs);
		}
	}

	private function buildClauseType(Breakdown $bk, $tabs) {
		if ($this->hasType()) {
			$this->type->build($bk, $tabs);
		}
	}

	private function buildClauseAllDistinctOnColumns(Breakdown $bk, $tabs) {
		$doDistinctClause = true;
		$parenthesisIsOpened = false;
		$doComma = false;
		$distinctIndex = 0;
		$totalDistinct = count($this->distinctOnColumns);

		if ($this->doCommaField) {
			$bk->append(',');
		}

		foreach ($this->distinctOnColumns as $distinctOn) {
			/** @var DistinctOn $distinctOn */
			if ($doComma) {
				$bk->append(',');
				$bk->line($tabs + 2);
			} else {
				$doComma = true;
			}

			if ($doDistinctClause) {
				$bk->append(' DISTINCT ON (');
				$doDistinctClause = false;
				$parenthesisIsOpened = true;
			}

			$distinctOn->build($bk, $tabs);

			if ($parenthesisIsOpened && $distinctIndex == $totalDistinct - 1) {
				$bk->append(' )');
				$parenthesisIsOpened = false;
			}

			++$distinctIndex;
		}
	}

	private function buildClauseAllDistinctColumns(Breakdown $bk, $tabs) {
		$doDistinctClause = true;
		$doComma = false;

		if ($this->doCommaField) {
			$bk->append(',');
		}

		foreach ($this->distinctColumns as $distinct) {
			/** @var Distinct $discinct */
			if ($doComma) {
				$bk->append(',');
				$bk->line($tabs + 2);
			} else {
				$doComma = true;
			}

			if ($doDistinctClause) {
				$bk->append(' DISTINCT');
				$doDistinctClause = false;
			}

			$discinct->build($bk, $tabs);
		}
	}

	private function buildClauseAllFunctions(Breakdown $bk, $tabs) {
		foreach ($this->functions as $function) {
			/** @var Functions $function */
			if ($this->doCommaField) {
				$bk->append(',');
			} else {
				$this->doCommaField = true;
			}

			$function->build($bk, $tabs);
		}
	}

	private function buildClauseAllFields(Breakdown $bk, $tabs) {
		foreach ($this->fields as $field) {
			/** @var Field $field */
			if ($this->doCommaField) {
				$bk->append(',');
			} else {
				$this->doCommaField = true;
			}

			$field->build($bk, $tabs);
		}
	}

	private function buildClauseInto(Breakdown $bk, $tabs) {
		if ($this->into != null) {
			$this->into->build($bk, $tabs);
		}
	}

	private function buildClauseAllSet(Breakdown $bk, $tabs) {
		$doSetClause = true;
		$doCommaSetClause = false;

		foreach ($this->set as $set) {
			/** @var Set $set */
			if ($doSetClause) {
				$bk->append(' SET');
				$doSetClause = false;
			}

			if ($doCommaSetClause) {
				$bk->append(',');
			} else {
				$doCommaSetClause = true;
			}

			$set->build($bk, $tabs);
		}
	}

	private function buildClauseFrom(Breakdown $bk, $tabs) {
		if ($this->from != null) {
			$bk->line($tabs + 1);
			$this->from->build($bk, $tabs);
		}
	}

	private function buildClauseAllJoin(Breakdown $bk, $tabs) {
		foreach ($this->joins as $join) {
			/** @var Join $join */
			$bk->line($tabs + 2);
			$join->build($bk, $tabs);
		}
	}

	private function buildClauseAllUnion(Breakdown $bk, $tabs) {
		foreach ($this->union as $union) {
			/** @var Union $union */
			$bk->line($tabs + 1);
			$union->build($bk, $tabs);
			$bk->line($tabs + 1);
		}
	}

	private function buildClauseAllIntersect(Breakdown $bk, $tabs) {
		foreach ($this->intersect as $intersect) {
			/** @var Intersect $intersect */
			$bk->line($tabs);
			$bk->line($tabs);
			$intersect->build($bk, $tabs);
			$bk->line($tabs);
		}
	}

	private function buildClauseAllExcept(Breakdown $bk, $tabs) {
		foreach ($this->except as $except) {
			/** @var Except $except */
			$bk->line($tabs);
			$except->build($bk, $tabs);
		}
	}

	private function buildClauseWhere(Breakdown $bk, $tabs) {
		if ($this->where != null) {
			$this->where->build($bk, $tabs);
		}
	}

	private function buildClauseAllGroupBy(Breakdown $bk, $tabs) {
		$doCommaGroupBy = false;
		$doGroupByClause = true;

		foreach ($this->groupBy as $groupBy) {
			/** @var GroupBy $groupBy */
			if ($doGroupByClause) {
				$bk->append(' GROUP BY');
				$doGroupByClause = false;
			}

			if ($doCommaGroupBy) {
				$bk->append(',');
			} else {
				$doCommaGroupBy = true;
			}

			$groupBy->build($bk, $tabs);
		}
	}

	private function buildClauseAllHaving(Breakdown $bk, $tabs) {
		if ($this->having != null) {
			$this->having->build($bk, $tabs);
		}
	}

	private function buildClauseAllValues(Breakdown $bk, $tabs) {
		foreach ($this->values as $value) {
			/** @var Values $value */
			if ($bk->doComma) {
				$bk->append(',');
			} else {
				$bk->doComma = true;
			}

			$value->build($bk, $tabs);
		}
	}

	private function buildClauseAllOrderBy(Breakdown $bk, $tabs) {
		$doOrderByClause = true;
		$doCommaOrderBy = false;

		foreach ($this->orderBy as $orderBy) {
			/** @var OrderBy $orderBy */
			if ($doOrderByClause) {
				$bk->line($tabs + 2);
				$bk->append(' ORDER BY');
				$doOrderByClause = false;
			}

			if ($doCommaOrderBy) {
				$bk->append(',');
			} else {
				$doCommaOrderBy = true;
			}

			$orderBy->build($bk, $tabs);
		}
	}

	private function buildClauseOffset(Breakdown $bk, $tabs) {
		if ($this->offset != null) {
			$this->offset->build($bk, $tabs);
		}
	}

	private function buildClauseLimit(Breakdown $bk, $tabs) {
		if ($this->limit != null) {
			$this->limit->build($bk, $tabs);
		}
	}

	private function buildClauseUpdate(Breakdown $bk, $tabs) {
		if ($this->update != null) {
			$this->update->build($bk, $tabs);
		}
	}

	private function inHelper($args, $conditionType) {
		$condition = $this->getLastCall();

		if (count($args) > 1 || !($args[0] instanceof SQL)) {
			if (is_array($args[0])) {
				$args = $args[0];
			}

			foreach ($args as $i => $arg) {
				$args[$i] = new Values($arg);
			}
		}

		if ($condition instanceof Condition) {
			$condition->equality($conditionType);
			$condition->right(self::instanceHelper('Field', $args));

			return $this->called($condition);
		} else {
			$condition = new Condition();
			$condition->equality($conditionType);
			$condition->right(self::instanceHelper('Field', $args));

			$field = new Field($condition);
			$this->fields []= $field;

			return $this->called($field);
		}
	}

	private function whereOp($value, $op, $convertToValue=true) {
		$condition = $this->getLastCondition();

		if ($condition == null) {
			throw new Exception('unable to get last CONDITION');
		}

		$condition->equality($op);

		if ($value !== null) {
			if ($convertToValue) {
				$value = new Values($value);
			}

			$condition->right(new Field($value));
		}

		return $this->called($condition);
	}

	private function function_($functionName, Field $field) {
		$function = new Functions($functionName, $field);
		$this->functions []= $function;
		return $this->called($function);
	}

	/**
	 * @return Join|null
	 */
	private function getLastJoin() {
		$size = count($this->callOrder);

		for ($i = $size - 1; $i >= 0; --$i) {
			if ($this->callOrder[$i] instanceof Join) {
				return $this->callOrder[$i];
			}
		}

		return null;
	}

	private function getLastCall() {
		$size = count($this->callOrder);
		return $size > 0 ? $this->callOrder[$size - 1] : null;
	}

	private function getLastCondition() {
		// give priority to having clause
		if ($this->having != null && count($this->having->conditions()) > 0) {
			$conditions = $this->having->conditions();
			$lastHavingCond = $conditions[count($conditions) - 1];

			if ($lastHavingCond instanceof Condition) {
				return $lastHavingCond;
			}
		}

		if ($this->where != null && count($this->where->conditions()) > 0) {
			$conditions = $this->where->conditions();
			$lastWhereCond = $conditions[count($conditions) - 1];

			if ($lastWhereCond instanceof Condition) {
				return $lastWhereCond;
			}
		}

		return null;
	}

	private static function instanceHelper($type, $args) {
		static $reflections = [];

		if (!array_key_exists($type, $reflections)) {
			$reflections[$type] = new \ReflectionClass("FluentSql\\{$type}");
		}

		return $reflections[$type]->newInstanceArgs($args);
	}

	protected function cacheEnabled() {
		return $this->cacheTtl > 0;
	}

	protected function getCache() {
		return new ProcessCache();
	}

	/**
	 * assumes $db has a method named "query", like mysqli
	 *
	 * @param $db
	 * @param BreakDown $breakDown
	 * @param callable $callback
	 * @throws \InvalidArgumentException
	 */
	protected function query($db, Breakdown $breakDown, callable $callback) {
		if (!method_exists($db, 'query')) {
			throw new \InvalidArgumentException;
		}

		$sql = $this->injectParams($db, $breakDown->getSql(), $breakDown->getParameters());
		return $callback($db, $db->query($sql));
	}

	protected function injectParams($db, $preparedSql, $params) {
		foreach ($params as $p) {
			$preparedSql = preg_replace('/\?/', "'".addslashes($p)."'", $preparedSql, 1);
		}

		return $preparedSql;
	}
}
