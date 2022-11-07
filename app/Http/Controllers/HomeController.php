<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use DB;
class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function enableMFA()
    {
        $authUser = auth()->user();
        if(!empty($authUser)){
            $authUserID    = $authUser->id;
            $authUserName  = str_replace(' ','_',$authUser->name);
            $authUserEmail = $authUser->email;
            $secret        = $authUserID.rand(0,200).$authUserEmail;
            $url           = 'https://www.authenticatorApi.com/pair.aspx?AppName=MyApp&AppInfo='.$authUserName.'&SecretCode='.$secret;
            $qrCode        = $this->httpRequest($url , 'GET');
            DB::table('users')->where('id',$authUserID)->update([ 'mfa_secret' => $secret ]);
            return view('mfa-code',[
                'qrCode'      => $qrCode,
                'secret'      => $secret,
                'mfaEnabled'  => $authUser->mfa_enabled,
            ]);

        } else {
            abort('401');
        }
    }

    public function verifyMFACode(Request $request)
    {
        $authUser = auth()->user();
        if(!empty($authUser)){
            $data          = $request->all();
            $authUserID    = $authUser->id;
            $authUserEmail = $authUser->email;
            $mfaEnabled    = $data['mfaEnabled'];
            $mfaSecret     = $data['secret'];
            $code          = $data['code'];
            if($mfaEnabled != $authUser->mfa_enabled) {
                if($mfaSecret == $authUser->mfa_secret){
                    $url      = 'https://www.authenticatorApi.com/Validate.aspx?Pin='.$code.'&SecretCode='.$mfaSecret;
                    $validate = $this->httpRequest($url , 'GET');
                    if($validate == 'True'){
                        DB::table('users')->where('id',$authUserID)->update(['mfa_enabled' => $mfaEnabled ]);
                        $msg = $data['mfaEnabled'] == 'Y' ? 'MFA activated successfully !': 'MFA disabled successfully';
                        return response()->json([ 'msg' => $msg ,'mfa_enabled' => $mfaEnabled , 'status' => true ],200);
                    } else {
                        return response()->json([ 'msg' => 'Invalid code' , 'status' => false ],200);
                    }
                  
                } else {
                    return response()->json([ 'msg' => 'Invalid code!' , 'status' => false ],200);
                }
            }
        } else {
            return response()->json([ 'msg' => 'Unauthorized!' , 'status' => false ],401);
        }
    }

    public function httpRequest($url , $type){
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $type,
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }

    public function checkUser(Request $request)
    {
        $data       = $request->all();
        $validate = Validator::make($data, [
            'email'    => 'required',
            'password' => 'required',
        ]);
        if($validate->fails()){
            return back()->withErrors($validate->errors())->withInput();
        }
        $email       = $data['email'];
        $password    = $data['password'];
        $mfa_code    = isset($data['mfa_code']) ? $data['mfa_code'] : '';

        $user        = DB::table('users')->where('email',$email)->select(['mfa_enabled','mfa_secret'])->first();
        $credentials = [
            'email'    => $email,
            'password' => $password,
        ];
        
        if(!empty($user)){
            $mfa_enabled = $user->mfa_enabled;
            $mfa_secret  = $user->mfa_secret;

            if($mfa_enabled == 'Y'){
                if(empty($mfa_code)){
                    return back()->with([
                        'mfa'        => 'Please fill the mfa code field.',
                        'mfaEnabled' => $mfa_enabled
                    ])->withInput();

                }
                $url      = 'https://www.authenticatorApi.com/Validate.aspx?Pin='.$mfa_code.'&SecretCode='.$mfa_secret;
                $validate = $this->httpRequest($url , 'GET');
                if($validate == 'True'){
                    $authenticatedUser = Auth::attempt($credentials);
                    if ($authenticatedUser) {
                        return redirect()->intended('home')->withSuccess([
                            'status' => 'You have Successfully loggedin'
                        ]);
                    }
                    return redirect("login")->with([
                        'message' => 'Invalid Credientails',
                        'mfaEnabled' => $mfa_enabled
                    ]);
                } else {
                    return back()->with([
                        'mfaError' => 'Invalid mfa code',
                        'mfaEnabled' => $mfa_enabled
                    ])->withInput();
                }
            }
            $authenticatedUser = Auth::attempt($credentials);
            if ($authenticatedUser) {
                return redirect()->intended('home')->withSuccess([
                    'status' => 'You have Successfully loggedin'
                ]);
            }

            return redirect("login")->with([
                'message' => 'Oppes! You have entered invalid credentials'
            ]);
        }
        return redirect("login")->with([
            'message' => 'Invalid Credientails'
        ]);
    } 
}
