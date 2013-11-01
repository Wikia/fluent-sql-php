<?php


class SampleQuery {

	function __construct($request = null) {
		if ($request != null) {
			// Parse the request object for any relevant data for this query
			//
			// Ensure that data is within valid ranges
			//
			// Ensure that defaults are properly set for all other data
			//
			// Ensure that SQL injection is not possible
		}
		return $this;
	}

	// Instead of exposing ALL methods for a query, we could just expose the basic ones
	// So that the where clause can be customized but the JOIN can not...
	function where() {
		return $this;
	}

	function limit() {
		return $this;
	}

	function cache() {
		// Default cache may be 1 day (or 1 hour)
		return $this;
	}
}

$query = (new SampleQuery())
	->where("startdate > 07-01-2013")
	->where("enddate < 07-07-2013")
	->limit(10)
	->cache(false);  // allow caller to turn off cache, etc

