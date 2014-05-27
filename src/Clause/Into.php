<?php

/**
 * Into
 *
 * @author Nelson Monterroso <nelson@wikia-inc.com>
 */

namespace FluentSql\Clause;

use FluentSql\Breakdown;

class Into implements ClauseInterface {
	protected $table;

	public function __construct($table) {
		$this->table = $table;
	}

	public function build(Breakdown $bk, $tabs) {
		$bk->line($tabs + 1);
		$bk->append(" INTO");
		$bk->append(" ".$this->table);
	}
}
