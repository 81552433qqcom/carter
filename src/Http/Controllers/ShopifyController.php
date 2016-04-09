<?php

namespace Woolf\Carter\Http\Controllers;

use Auth;
use Config;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Shopify;
use Woolf\Carter\Http\Middleware\RedirectIfLoggedIn;
use Woolf\Carter\Http\Middleware\RedirectToLogin;
use Woolf\Carter\Http\Middleware\RequestHasShopDomain;
use Woolf\Carter\Http\Middleware\VerifyChargeAccepted;
use Woolf\Carter\Http\Middleware\VerifySignature;
use Woolf\Carter\Http\Middleware\VerifyState;
use Woolf\Shophpify\Client;
use Woolf\Shophpify\Endpoint;
use Woolf\Shophpify\Resource\OAuth;
use Woolf\Shophpify\Resource\RecurringApplicationCharge;
use Woolf\Shophpify\Resource\Shop;

class ShopifyController extends Controller
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $installRules = ['shop' => 'required|unique:users,domain|max:255'];

    protected $installMessages = ['shop.unique' => 'Store has already been registered'];

    public function __construct()
    {
        $this->middleware(RequestHasShopDomain::class, [
            'only' => ['install']
        ]);

        $this->middleware(VerifyState::class, [
            'only' => ['register']
        ]);

        $this->middleware(VerifySignature::class, [
            'only' => ['register', 'login']
        ]);

        $this->middleware(RedirectIfLoggedIn::class, [
            'only' => ['install', 'registerStore', 'register', 'login']
        ]);

        $this->middleware(RedirectToLogin::class, [
            'only' => ['dashboard']
        ]);

        $this->middleware(VerifyChargeAccepted::class, [
            'only' => ['dashboard']
        ]);
    }

    public function install(Request $request, OAuth $oauth)
    {
        $this->validate($request, $this->installRules, $this->installMessages);

        session(['state' => Str::random(40)]);

        $url = $oauth->authorizationUrl(
            config('carter.shopify.client_id'),
            implode(',', config('carter.shopify.scopes')),
            route('shopify.register'),
            session('state')
        );

        return redirect($url);
    }

    public function registerStore()
    {
        return view('carter::shopify.auth.register');
    }

    public function register(Request $request, OAuth $oauth)
    {
        $accessToken = $oauth->requestAccessToken(
            config('carter.shopify.client_id'),
            config('carter.shopify.client_secret'),
            $request->code
        );

        $shop = app(Shop::class, [
            'client' => new Client($accessToken)
        ])->get(['id', 'name', 'email', 'domain']);

        $shop['shopify_id'] = $shop['id'];
        unset($shop['id']);

        $user = app('carter_user')->create($shop + ['password' => bcrypt(Str::random(20))]);

        auth()->login($user);

        $charge = app(RecurringApplicationCharge::class)->create(config('carter.shopify.plan'));

        return redirect($charge['confirmation_url']);
    }

    public function activate(Request $request, RecurringApplicationCharge $charge, Endpoint $endpoint)
    {
        $id = $request->get('charge_id');

        if ($charge->isAccepted($id)) {
            $charge->activate($id);
            auth()->user()->update(['charge_id' => $id]);
        }

        return redirect($endpoint->build('admin/apps'));
    }

    public function login(Request $request)
    {
        $user = app('carter_user')->whereDomain($request->get('shop'))->first();

        auth()->login($user);

        return redirect()->route('shopify.dashboard');
    }

    public function dashboard()
    {
        return view('carter::shopify.app.dashboard', ['user' => auth()->user()]);
    }
}
