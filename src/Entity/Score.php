<?php

namespace Wikisource\WsContest\Entity;

class Score extends Model {

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'contest_id',
		'index_page_id',
		'user_id',
		'revision_id',
	];

	protected $revisionUrl;

	public function indexPage() {
		return $this->belongsTo( IndexPage::class );
	}

	public function contest() {
		return $this->belongsTo( Contest::class );
	}

	public function user() {
		return $this->belongsTo( User::class );
	}

	/**
	 * @return string
	 */
	public function getRevisionUrl() {
		if ( $this->revisionUrl ) {
			return $this->revisionUrl;
		}
		$this->revisionUrl = 'https://' . $this->indexPage->getDomainName()
			. '/w/index.php?oldid=' . $this->revision_id . '&diff=prev';
		return $this->revisionUrl;
	}
}
