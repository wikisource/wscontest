<?php

namespace Wikisource\WsContest\Entity;

class User extends Model {

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'name' ];

	/** @var int[] */
	protected static $userIds;

	public function admins() {
		return $this->belongsToMany( Contest::class, 'admins' );
	}

	/**
	 * @param string $username
	 * @return int
	 */
	public static function getIdFromUsername( string $username ) {
		if ( isset( static::$userIds[$username] ) ) {
			return static::$userIds[$username];
		}
		static::$userIds[$username] = static::firstOrCreate( [ 'name' => $username ] )->id;
		return static::$userIds[$username];
	}

}
