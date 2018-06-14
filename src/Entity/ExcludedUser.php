<?php

namespace Wikisource\WsContest\Entity;

class ExcludedUser extends Model {

	public function user() {
		return $this->belongsTo( User::class );
	}
}
