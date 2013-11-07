<?php
namespace FluentSql;

require_once(__DIR__.'/../src/init.php');

function test() {
	$expected = __sanitize("SELECT * FROM products WHERE price IS NOT NULL");
	$actual = __sanitize((new SQL())->SELECT_ALL()->FROM("products")->WHERE("price")->IS_NOT_NULL()->build());

	var_dump($expected == $actual);
}

function test2() {
	$expected =
		" WITH LatestOrders AS (" .
		"		SELECT SUM ( COUNT ( ID ) )," .
		"				COUNT ( MAX ( n_items ) ), " .
		"				CustomerName " .
		"			FROM dbo.Orders" .
		"			RIGHT JOIN Customers" .
		"				ON Orders.Customer_ID = Customers.ID " .
		"			LEFT JOIN Persons" .
		"				ON Persons.name = Customer.name" .
		"				AND Persons.lastName = Customer.lastName" .
		"			GROUP BY CustomerID" .
		"		) ".
		" SELECT ".
		"    Customers.*, ".
		"    Orders.OrderTime AS LatestOrderTime, ".
		"    ( SELECT COUNT ( * ) " .
		"		FROM dbo.OrderItems " .
		"		WHERE OrderID IN ".
		"        ( SELECT ID FROM dbo.Orders WHERE CustomerID = Customers.ID ) ) ".
		"            AS TotalItemsPurchased ".
		" FROM dbo.Customers " .
		" INNER JOIN dbo.Orders ".
		"        USING ID" .
		" WHERE ".
		"	Orders.n_items > 0 ".
		"   AND Orders.ID IN ( SELECT ID FROM LatestOrders )" ;

	$actual =
		StaticSQL::WITH("LatestOrders",
			StaticSQL::SELECT("CustomerName")
				->SUM(StaticSQL::COUNT("ID"))
				->COUNT(StaticSQL::MAX("n_items"))
				->FROM("dbo.Orders")
				->RIGHT_JOIN("Customers")
					->ON("Orders.Customer_ID", "Customers.ID")
				->LEFT_JOIN("Persons")
					->ON("Persons.name", "Customer.name")
					->AND_("Persons.lastName", "Customer.lastName")
				->GROUP_BY("CustomerID")
		)
		->SELECT()
			->FIELD("Customers.*")
			->FIELD("Orders.OrderTime")->AS_("LatestOrderTime")
			->FIELD(StaticSQL::SELECT()->COUNT("*")
							->FROM("dbo.OrderItems")
							->WHERE("OrderID")->IN(
								StaticSQL::SELECT("ID")
								->FROM("dbo.Orders")
								->WHERE("CustomerID")->EQUAL_TO("Customers.ID"))

						)->AS_("TotalItemsPurchased")
			->FROM("dbo.Customers")
			->INNER_JOIN("dbo.Orders")
				->USING("ID")
			->WHERE("Orders.n_items")->GREATER_THAN(0)
			->AND_("Orders.ID")->IN(StaticSQL::SELECT("ID")->FROM("LatestOrders"))
		->build();

	$expected = __sanitize($expected);
	$actual = __sanitize($actual);

	var_dump($expected == $actual);
}

function __sanitize($string) {
	return trim(preg_replace('/\s+/', ' ', $string))	;
}

test();
test2();