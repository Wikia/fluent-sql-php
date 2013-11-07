<?php
/**
 * Cache
 *
 * <insert description here>
 *
 * @author Nelson Monterroso <nelson@wikia-inc.com>
 */

namespace FluentSql;

abstract class Cache {
	protected function getKey(Breakdown $breakDown) {
		return md5($breakDown->getSql());
	}

	abstract public function get(Breakdown $breakDown, $key=null);
	abstract public function set(Breakdown $breakDown, $value, $ttl, $key=null);
}