<?php

class Breakdown {
	private $sql;
	private $parameters;
	public $doComma = false;

	public function __construct($sql = '', $parameters = []){
		$this->sql = $sql;
		$this->parameters = $parameters;
	}

	public function getSql(){
		return $this->sql;
	}

	public function append($str){
		$this->sql .= $str;
	}

	public function addParameter($parameter){
		$this->parameters[] = $parameter;
	}

	public function line($tabs){
		$this->append(Clause::line($tabs));
	}

	public function tabs($tabs){
		$this->append(Clause::tabs($tabs));
	}

	public function getParameters() {
		return $this->parameters;
	}
}
