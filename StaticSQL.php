<?php

require_once("SQL.php");

class StaticSQL {
	static $singleton;

	public function SQL() {
		if ($this->singleton == null) {
			$this->singleton = new SQL();
		}
		return $this->singleton;
	}

	public static function WITH($name, SQL $sql){
		return (new SQL())->WITH($name, $sql);
	}

	public static function WITH_RECURSIVE($name, SQL $sql){
		return new SQL().WITH_RECURSIVE($name, $sql);
	}

	public static function SELECT(){
		return new SQL().SELECT();
	}
	public static function SELECT(/*...*/){
		return new SQL().SELECT(func_get_args());
	}

	public static function SELECT_ALL(){
		return new SQL().SELECT_ALL();
	}

	public static function COUNT($sql){
		return new SQL().COUNT($sql);
	}

	public static function MIN($sql){
		return new SQL().MIN($sql);
	}

	public static function MAX($sql){
		return new SQL().MAX($sql);
	}

	public static function SUM($sql){
		return new SQL().SUM($sql);
	}
	public static function AVG($sql){
		return new SQL().AVG($sql);
	}

	public static function LOWER($sql){
		return new SQL().LOWER($sql);
	}
	public static function UPPER($sql){
		return new SQL().UPPER($sql);
	}


}