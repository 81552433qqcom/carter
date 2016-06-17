<?php

namespace Woolf\Carter\Http\Middleware;

use Closure;

class RequestHasShopDomain
{

    public function handle($request, Closure $next)
    {
        if (! $request->has('shop')) {
            return redirect()->route('shopify.signup');
        }

        //check if login domain is same as request domain or not
        if(auth()->check())
        {
        	$shop = $request->input('shop');
        	$shop_login =  auth()->user()->domain;

        	if($shop != $shop_login)
        	{
        		//logout current login
        		auth()->logout();	
        	}
        }

        return $next($request);
    }
}
