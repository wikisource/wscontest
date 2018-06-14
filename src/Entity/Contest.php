<?php

namespace Wikisource\WsContest\Entity;

use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;

class Contest extends Model {

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'name', 'start_date', 'end_date' ];

//	public function __construct( array $attributes = [] ) {
//		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
//		$attributes['start_date'] = $now->format( 'Y-m-d 00:00:01' );
//		$attributes['end_date'] = $now->add( new DateInterval( 'P14D' ) )
//			->format( 'Y-m-d 23:59:59' );
//		parent::__construct( $attributes );
//	}

	public function admins() {
		return $this->belongsToMany(User::class, 'admins');
	}

	public function excludedUsers() {
		return $this->belongsToMany(User::class, 'excluded_users');
	}

	public function indexPages() {
		return $this->belongsToMany(IndexPage::class, 'contest_index_pages' );
	}

	public function scores() {
		$this->hasMany( Score::class );
	}

	public function getStartDateAttribute( $value ) {
		$startDate = new DateTime( $value, new DateTimeZone( 'UTC' ) );
		return $startDate->format('Y-m-d H:i:s');
	}

	public function getEndDateAttribute( $value ) {
		$endDate = new DateTime( $value, new DateTimeZone( 'UTC' ) );
		if ( !$value && $this->start_date) {
			return $endDate->add( new DateInterval( 'P14D' ) );
		}
		return $endDate->format('Y-m-d H:i:s');
	}

	public function scopeHasAdmin( Builder $query, $username ) {
		return $query->whereHas( 'admins', function ( Builder $query ) use ($username) {
			//$admins->whereHas( 'user', function ( Builder $user ) use ($username) {
				$query->where('name', 'LIKE', $username);
			//});
		} );
	}

	public function scopeScores( Builder $query ) {
		return $query->with( 'contestIndexPages' );

//		'SELECT'
//		. '   u.name AS username,'
//		. '   u.id AS user_id,'
//		. '   SUM(ips.points) AS points,'
//		. '   SUM(ips.contributions) AS contributions,'
//		. '   SUM(ips.validations) AS validations'
//		. ' FROM index_page_scores ips'
//		. '   JOIN contest_index_pages cip ON cip.index_page_id = ips.index_page_id'
//		. '   JOIN users u ON u.id = ips.user_id'
//		. ' WHERE cip.contest_id = :cid'
//		. ' GROUP BY u.id'
//		. ' ORDER BY SUM(ips.points), SUM(ips.contributions), SUM(ips.validations)'
	}
}
