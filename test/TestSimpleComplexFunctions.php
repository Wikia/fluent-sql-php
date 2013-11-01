<?php

include_once("../SQL.php");

function test() {
	$expected = "SELECT * FROM products WHERE price IS NOT NULL";
	$actual = (new SQL())->SELECT_ALL()->FROM("products")->WHERE("price")->IS_NOT_NULL()->build()->getSql();
	var_dump($expected == $actual);
}

test();
