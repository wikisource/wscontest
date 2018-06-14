<?php

namespace Wikisource\WsContest\Entity;

class ContestIndexPage extends Model {

	public function contest() {
		return $this->belongsTo( Contest::class );
	}

	public function indexPage() {
		return $this->belongsTo( IndexPage::class );
	}
}
