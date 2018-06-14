<?php

namespace Wikisource\WsContest\Entity;

use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class Model extends EloquentModel {

	/**
	 * None of our models are timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

}
