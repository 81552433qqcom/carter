<?php

namespace Woolf\Carter;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Woolf\Shophpify\Client;
use Woolf\Shophpify\Resource\OAuth;
use Woolf\Shophpify\Resource\Shop;

class RegisterShop
{
    protected $oauth;

    protected $request;

    public function __construct(OAuth $oauth, Request $request)
    {
        $this->oauth = $oauth;

        $this->request = $request;
    }

    public function execute()
    {
        //check if exists or not, if exists, then escape
        $accessToken = $this->getAccessToken();
        //get shop info
        $shop = $this->shop($accessToken);

        //check
        if(empty($shop) || empty($shop['id']))
        {
            return app('carter_user')->create($shop);
        }
        else
            return $shop['id'];
       
    }

    protected function shop($accessToken)
    {
        $shop = app(Shop::class, ['client' => new Client($accessToken)])->get(['id', 'name', 'email', 'domain']);

        return [
            'shopify_id'   => $shop['id'],
            'name'         => $shop['name'],
            'email'        => $shop['email'],
            'domain'       => $shop['domain'],
            'access_token' => $accessToken,
            'password'     => bcrypt(Str::random(20))
        ];
    }

    protected function getAccessToken()
    {
        return $this->oauth->requestAccessToken(
            $this->config('client_id'),
            $this->config('client_secret'),
            $this->request->code
        );
    }

    protected function config($key)
    {
        return config("carter.shopify.{$key}");
    }
}
