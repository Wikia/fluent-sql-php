<?php

include_once("Clause.php");

class SQL {

	protected $callOrder = [];
	protected $withQueries = [];
	protected $type;
	protected $fields = [];
	protected $functions = [];
	protected $cases = [];
	protected $set = [];
	protected $distinctColumns = [];
	protected $distinctOnColumns = [];

	protected $from;
	protected $update;
	protected $into;

	protected $doCommaField = false;

	protected $values = [];
	protected $joins = [];
	protected $union = [];
	protected $intersect = [];
	protected $except = [];

	protected $where;

	protected $orderBy = [];
	protected $groupBy = [];
	protected $having = [];
	protected $returning = [];

	protected $limit;
	protected $offset;

	private function called($call) {
		$callOrder []= $call;
		return $this;
	}

	public function getType(){
		return $this->type->type;
	}

	public function WITH($name, SQL $sql){
		$with = new With($name,$sql,false);
		$this->withQueries []= $with;
		return $this->called($with);
	}

	public function WITH_RECURSIVE($name, SQL $sql){
		$with = new With($name,$sql,true);
		$this->withQueries []= $with;
		return $this->called($with);
	}

	public function SELECT_ALL(){
		return $this->SELECT("*");
	}

	public function SELECT(){
		$argc = func_num_args();
		$argv = func_get_args();

		$this->type = new Type(Type::SELECT);
		$this->called($this->type);

		if ($argc > 0 ) {
			foreach ($argv as $col) {
				$this->FIELD($col);
			}
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

	public function DISTINCT(){
		$argv = func_get_args();
		foreach ($argv as $col) {
			$distinct = new Distinct($col);
			$this->distinctColumns []= $distinct;
			$this->called($distinct);
		}
		return $this;
	}

	public function DISTINCT_ON(){
		$argv = func_get_args();
		foreach ($argv as $col) {
			$distinctOn = new DistinctOn($col);
			$this->distinctOnColumns []= $distinctOn;
			$this->called($distinctOn);
		}
		return $this;
	}

	public function FIELD(){
		$argv = func_get_args();
		foreach ($argv as $sql) {
			$field = new Field($sql);
			$this->fields []= $field;
			$this->called($field);
		}
		return $this;
	}

	public function SUM($sql){
		return new Functions(Functions.SUM, new Field($sql));
	}

	public function COUNT($sql){
		return new Functions(Functions.COUNT, new Field($sql));
	}

	public function MAX($sql){
		return new Functions(Functions.MAX, new Field($sql));
	}

	public function MIN($sql){
		return new Functions(Functions.MIN, new Field($sql));
	}

	public function AVG($sql){
		return new Functions(Functions.AVG, new Field($sql));
	}

	public function LOWER($sql){
		return new Functions(Functions.LOWER, new Field($sql));
	}
	public function UPPER($sql){
		return new Functions(Functions.UPPER, new Field($sql));
	}

/* don't think these are necessary

	private SQL function(String functionName, Field field){
		Function function  = new Function(functionName, field);
		return function(function);
	}

	private SQL function(Function function){
		this.functions.add(function);
		return called(function);
	}

*/

	// TODO: fuck. "AS" is a keyword
	public function ALIAS($columnAs){
		$lastCall = $this->getLastCall();
		if($lastCall instanceof Field){
			$lastCall->columnAs = $columnAs;
		}
		if($lasCall instanceof Functions){
			$lastCall->functionAs = $columnAs;
		}
		return $this;
	}


}

$w = new SQL();
$s = (new SQL())->WITH("foo", $w);
