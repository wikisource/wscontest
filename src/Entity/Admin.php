<?php

namespace Wikisource\WsContest\Entity;

class Admin extends Model {

	public function user() {
		return $this->belongsTo(User::class);
	}

}
