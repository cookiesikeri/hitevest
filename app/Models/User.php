<?php

namespace App\Models;

use App\Traits\ManagesTransactions;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\UsesUuid;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\URL;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class User extends Authenticatable implements JWTSubject, Searchable
{
    use Notifiable, UsesUuid, ManagesTransactions;

//    protected $appends = ['image_url'];

    protected $guard_name = 'api';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id'
    ];

        /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

        /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token','transaction_pin',
    ];

    public function getSearchResult(): SearchResult
    {
        return new \Spatie\Searchable\SearchResult(
            $this,
            $this->name
        );
    }

    public function customer_verification()
    {
        return $this->hasOne('App\Models\CustomerValidation');
    }



   public function hasRole()
   {
       return $this->role->name;
   }


    public function wallet(){

        return $this->hasOne('App\Models\Wallet');
    }


    public function secret_q_and_a(){
        return $this->hasOne(UserSecretQAndA::class);
    }

    public function referrals(){
        return $this->hasMany(Referral::class, 'referrer_id');
    }


    public function referral_code(){
        return $this->hasOne(ReferralCode::class);
    }


    public function useractivities(){
        return $this->hasMany(UserActivity::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function accounttype() {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function transactions() {
        return $this->hasMany(Transaction::class);
    }

}
