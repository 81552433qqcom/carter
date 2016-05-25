<?php

namespace Woolf\Carter;

trait ShopOwner
{

    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = $value;
    }

    public function getAccessTokenAttribute()
    {
        //return decrypt($this->attributes['access_token']);
        return $this->attributes['access_token'];
    }

}
