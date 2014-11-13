<?php

/**
 * OnDuplicateKeyUpdate
 *
 * @author Armon Rabiyan <armon@wikia-inc.com>
 */

namespace FluentSql\Clause;

use FluentSql\Breakdown;

class OnDuplicateKeyUpdate implements ClauseInterface {
	protected $columns;

	public function __construct( $columns ) {
		$this->columns = $columns;
	}

	public function build( Breakdown $bk, $tabs ) {
		$bk->line( $tabs + 1 );
		$bk->append( ' ON DUPLICATE KEY UPDATE' );
		$bk->append( ' ' . $this->getAssignmentsClause() );
	}

	protected function getAssignmentsClause() {
		$pairs = [];
		foreach ( $this->columns as $column => $value ) {
			$quotedValue = is_string( $value ) ? "'$value'" : $value;
			$pairs[] = "$column = $quotedValue";
		}

		return implode( ', ', $pairs );
	}
}
