<?php

namespace Wikisource\WsContest\Entity;

class User extends Model {

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'name' ];

	public function admins() {
		return $this->belongsToMany(Contest::class, 'admins');
	}

//	public function getIdFromUsername( $username ) {
//		if ( isset( $this->userIds[$username] ) ) {
//			return $this->userIds[$username];
//		}
//		// Create the user row if required.
//		$userCreate = $this->db->prepare( 'INSERT IGNORE INTO users SET name=:name' );
//		$userCreate->execute( [ 'name' => $username ] );
//		// Get its ID.
//		$userFind = $this->db->prepare( 'SELECT id FROM users WHERE name=:name' );
//		$userFind->execute( [ 'name' => $username ] );
//		$this->userIds[$username] = $userFind->fetchColumn();
//
//		return $this->userIds[$username];
//	}

}
