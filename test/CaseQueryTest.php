<?php
namespace FluentSql;

require_once(__DIR__.'/init.php');

class CaseQueryTest extends FluentSqlTest {
	public function testSimpleValueCase() {
		$expected = "
			SELECT
				(
					CASE ?
						WHEN ? THEN ?
						WHEN ? THEN ?
						ELSE ?
					END
				) AS someCol
		";

		$actual = (new SQL)
			->SELECT()
				->CASE_(1)
					->WHEN(1)->THEN('one')
					->WHEN(2)->THEN('two')
					->ELSE_('unknown')
				->AS_('someCol');

		$this->assertEquals($expected, $actual);
	}

	public function testSimpleConditionCase() {
		$expected = "
			SELECT
				(
					CASE
						WHEN ? > ? THEN ?
						ELSE ?
					END
				)
		";

		$actual = (new SQL)
			->SELECT()
				->CASE_()
					->WHEN(1)->GREATER_THAN(0)->THEN('one')
					->ELSE_('unknown');

		$this->assertEquals($expected, $actual);
	}
}