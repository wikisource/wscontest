<?php

namespace Wikisource\WsContest\Entity;

use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

class Contest extends Model {

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'name', 'start_date', 'end_date' ];

	/**
	 * Contest constructor.
	 * @param array $attributes
	 */
	public function __construct( array $attributes = [] ) {
		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$twoWeeks = new DateInterval( 'P14D' );
		$this->setRawAttributes(
			[
				'start_date' => $now->format( 'Y-m-d 00:00:01' ),
				'end_date' => $now->add( $twoWeeks )->format( 'Y-m-d 23:59:59' ),
			],
			true
		);
		parent::__construct( $attributes );
	}

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function admins() {
		return $this->belongsToMany( User::class, 'admins' );
	}

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function excludedUsers() {
		return $this->belongsToMany( User::class, 'excluded_users' );
	}

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function indexPages() {
		return $this->belongsToMany( IndexPage::class, 'contest_index_pages' );
	}

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function scores() {
		return $this->hasMany( Score::class );
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public function getStartDateAttribute( $value ) {
		if ( $value ) {
			$startDate = new DateTime( $value, new DateTimeZone( 'UTC' ) );
			return $startDate->format( 'Y-m-d H:i:s' );
		}
		return $value;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public function getEndDateAttribute( $value ) {
		if ( $value ) {
			$endDate = new DateTime( $value, new DateTimeZone( 'UTC' ) );
			$value = $endDate->format( 'Y-m-d H:i:s' );
		}
		return $value;
	}

	/**
	 * @param Builder $query
	 * @param string $username
	 * @return Builder|static
	 */
	public function scopeHasAdmin( Builder $query, $username ) {
		return $query->whereHas( 'admins', function ( Builder $query ) use ( $username ) {
				$query->where( 'name', '=', $username );
		} );
	}

	/**
	 * @param Builder $query
	 * @return Builder
	 */
	public function scopeInProgress( Builder $query ) {
		return $query
			->where( 'start_date', '<=', new Expression( 'NOW()' ) )
			->where( 'end_date', '>=', new Expression( 'NOW()' ) );
	}
}
