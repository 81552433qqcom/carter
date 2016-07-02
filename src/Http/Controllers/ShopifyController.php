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
use Woolf\Carter\RegisterShop;
use Woolf\Shophpify\Endpoint;
use Woolf\Shophpify\Client;
use Woolf\Shophpify\Resource\OAuth;
use Woolf\Shophpify\Resource\RecurringApplicationCharge;
use Woolf\Shophpify\Resource\Webhook;
use Log;
use URL;

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
            'only' => ['install', 'register', 'login']
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


    public function uninstall(Request $request)
    {
        //get data
        $data = $request->all();
        $shop = $data['domain'];

        if(empty($shop) == false)
        {
            Log::info('Uninstall data', ['shop' => $shop ]);

            //remove shop user from db
            app('carter_user')->whereDomain($shop)->delete();
        }
        else
        {
            
        }
        
    }


    public function registerStore(Request $request)
    {
        $shop = $request->get('shop_url');
        if(empty($shop))
        {
            $shop = $request->get('shop');
        }

        if(empty($shop))
        {
            return view('carter::shopify.auth.register');
        }
        else
        {
            //install
            return redirect()->route('shopify.install',['shop' => $shop]);
        }


        
    }

    public function register(RegisterShop $registerShop)
    {
        auth()->login($registerShop->execute());

        //create hooks
        $this->registerHook();

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
        $shop = $request->get('shop');
        $user = app('carter_user')->whereDomain($shop)->first();
        if(empty($user) == false)
        {
            auth()->login($user);
            return redirect()->route('shopify.dashboard',['shop_url' => $shop]);
        }
        else
        {
            return redirect()->route('shopify.signup',['shop_url' => $shop]);
        }
        
    }

    public function logout()
    {
       auth()->logout();
       return redirect()->route('shopify.signup');
    }

    public function dashboard(Request $request)
    {
        $shop_url = $request->get('shop');
        return view('carter::shopify.app.dashboard', ['user' => auth()->user(),'shop_url' => $shop_url]);
    }


    protected function registerHook()
    {

        $hooks_register = config('carter.shopify.hooks_register');
        foreach($hooks_register as $each_route => $each_hook)
        {
            //register uninstall hook
            $url = URL::route($each_route);
            Log::info($url);

            $webhook = ['topic' => $each_hook['topic'],"address" => $url , 'format' => $each_hook['format']];
            Log::info('hook return',['data' => app(Webhook::class)->create($webhook)]);
        
        }
   
        Log::info('hooks all',['all hooks' => app(Webhook::class)->all()]);
        
    }
}
