<?php

namespace App\Http\Controllers\Api;

use App\Traits\ApiResponser;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\{
    Attachment,
    Project,
    User,
    UserInterest
};
use App\Notifications\Otp;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

class AuthController extends Controller
{
    use ApiResponser;

    // private $verified_code = 123456; // mt_rand(100000,900000);


    private $verified_code;

    public function __construct()
    {
        $this->verified_code = random_int(100000, 900000);  // 123456
    }
    
    /** User login */
    public function login(Request $request, PaymentService $paymentService)
    {
        $this->validate($request, [
            'user_type'   =>  'required|in:user,influencer',
            'email'       =>  'required|email'
        ]);

        $user = User::withTrashed()->where('email', $request->email)->first();

        if (empty($user)) { // Register
            DB::beginTransaction();
            $created =  User::create($request->only('user_type', 'email') + ['verified_code' => $this->verified_code]);

            if ($created) {

                try {
                    $created->subject =  'Account Verification';
                    $created->message =  'Please use the verification code below to sign up. ' . '<br> <br> <b>' . $created->verified_code . '</b>';

                    Notification::send($created, new Otp($created));
                } catch (\Exception $exception) {
                }

                $data = [
                    'user_id' => $created->id
                ];

                if ($request->user_type == 'user') {
                    $customer = $paymentService->createCustomer($created->id);
                    User::whereId($created->id)->update(['customer_id' => $customer->id]);
                }

                if ($request->user_type == 'influencer') {
                    $accountId = $paymentService->createExpressAccount()?->id;
                    User::whereId($created->id)->update(['account_id' => $accountId]);
                }

                DB::commit();
                return $this->successDataResponse('Please enter verification', $data, 200);
            } else {
                DB::rollBack();
                return $this->errorResponse('Something went wrong.', 400);
            }
        } else { // Login 
            if ($user->deleted_at != null) {
                return $this->errorDataResponse('Your account has been deleted as per your request.', ['user_id' => $user->id, 'is_deleted' => 1], 400);
            } else {
                if ($user->user_type == $request->user_type) {
                    if ($user->is_verified == 1) {
                        if ($user->is_blocked == 0) {
                            $user->verified_code = $this->verified_code;
                            $user->save();
                            try {
                                $user->subject =  'Sign in Verification';
                                $user->message =  'Please use the verification code below to sign in. ' . '<br> <br> <b>' . $user->verified_code . '</b>';

                                Notification::send($user, new Otp($user));
                            } catch (\Exception $exception) {
                            }

                            $data = [
                                'user_id' => $user->id
                            ];
                            return $this->successDataResponse('Please enter verification.', $data, 200);
                        } else {
                            return $this->errorResponse('Your account is blocked.', 400);
                        }
                    } else {
                        $data = [
                            'user_id' => $user->id
                        ];
                        return $this->successDataResponse('Your account is not verfied.', $data, 200);
                    }
                } else {
                    return $this->errorResponse('This email has already been registered as a ' . $user->user_type, 400);
                }
            }
        }
    }

    /** Social login */
    public function socialLogin(Request $request, PaymentService $paymentService)
    {
        $this->validate($request, [
            'social_type'       =>  'required|in:google,apple,facebook,phone',
            'social_token'      =>  'required',
            'device_type'       =>  'in:ios,android,web',
            'user_type'         =>  'required|in:user,influencer'
        ]);

        try {
            DB::beginTransaction();
            $user = User::withTrashed()->where('social_token', $request->social_token)->first();

            if (!empty($user)) {
                if ($user->deleted_at == null) {
                    if ($user->user_type == $request->user_type) {
                        if ($user->is_blocked == 0) {
                            $user->timezone = $request->timezone;
                            $user->device_type = $request->device_type;
                            $user->device_token = $request->device_token;
                            $user->save();
                        } else {
                            return $this->errorResponse('Your account is blocked.', 400);
                        }
                    } else {
                        return $this->errorResponse('This email has already been registered as a ' . $user->user_type, 400);
                    }
                } else {
                    return $this->errorDataResponse('Your account has been deleted as per your request.', ['user_id' => $user->id, 'is_deleted' => 1], 400);
                }
            } else {
                $user = new User;
                $user->timezone = $request->timezone;
                $user->social_type = $request->social_type;
                $user->social_token = $request->social_token;
                $user->user_type = $request->user_type;
                $user->phone_number = $request->phone_number;
                $user->is_verified = '1';
                $user->is_social = '1';
                $user->is_profile_complete = '0';
                $user->device_type = $request->device_type;
                $user->device_token = $request->device_token;
                $user->save();

                if ($request->user_type == 'user') {
                    $customer = $paymentService->createCustomer($user->id);
                    User::whereId($user->id)->update(['customer_id' => $customer->id]);
                }

                if ($request->user_type == 'influencer') {
                    $accountId = $paymentService->createExpressAccount()?->id;
                    User::whereId($user->id)->update(['account_id' => $accountId]);
                }
            }

            $token = $user->createToken('AuthToken');
            $user = User::whereId($user->id)->first();
            $userResource = new UserResource($user);

            DB::commit();

            $message = 'Social login successfully.';
            if ($request->social_type == 'phone') {
                $message = 'Phone login success.';
            }
            return $this->loginResponse($message, $token->plainTextToken, $userResource);
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** User verification */
    public function verification(Request $request)
    {
        $this->validate($request, [
            'user_id'       =>  'required|exists:users,id',
            'verified_code' =>  'required',
            'device_type'   =>  'in:ios,android,web'
        ]);

        $userExists = User::whereId($request->user_id)->where('verified_code', $request->verified_code)->first();

        if (!empty($userExists)) {
            $updateUser = User::whereId($request->user_id)->where('verified_code', $request->verified_code)->update(['device_type' => $request->device_type, 'device_token' => $request->device_token, 'is_verified' => '1', 'verified_code' => null, 'timezone' => $request->timezone]);
            if ($updateUser) {
                $user = User::find($request->user_id);
                $token = $user->createToken('AuthToken');

                $userResource = new UserResource($user);
                if ($userExists->is_verified == '1') {
                    return $this->loginResponse(ucfirst($user->user_type) . ' login successfully.', $token->plainTextToken, $userResource);
                } else {
                    return $this->loginResponse(ucfirst($user->user_type) . ' register successfully.', $token->plainTextToken, $userResource);
                }
            } else {
                return $this->errorResponse('Something went wrong.', 400);
            }
        } else {
            return $this->errorResponse('Invalid otp.', 400);
        }
    }

    /** Resend code */
    public function reSendCode(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required|exists:users,id'
        ]);

        $user = User::whereId($request->user_id)->first();
        $user->verified_code = $this->verified_code;

        if ($user->save()) {
            return $this->successResponse('We have resend OTP verification code at your email address.', 200);
        } else {
            return $this->errorResponse('Something went wrong.', 400);
        }
    }

    /** Complete profile */
    public function completeProfile(Request $request, PaymentService $paymentService)
    {
        $this->validate($request, [
            'profile_image'             =>    'mimes:jpeg,png,jpg',
            'cover_image'               =>    'mimes:jpeg,png,jpg',
            'gender'                    =>    'in:male,female,other',
            'interest_id'               =>    'array'
        ]);

        try {
            $authUser = auth()->user();
            $authId = $authUser->id;
            $completeProfile = $request->only(
                'first_name',
                'last_name',
                'gender',
                'profile_image',
                'cover_image',
                'phone_number',
                'date_of_birth',
                'website_link',
                'interest',
                'expertise',
                'country',
                'state',
                'city',
                'about'
            );

            if ($request->hasFile('profile_image')) {
                $profile_image = strtotime("now") . mt_rand(100000, 900000) . '.' . $request->profile_image->getClientOriginalExtension();
                $request->profile_image->move(public_path('/media/profile_image'), $profile_image);
                $file_path = '/media/profile_image/' . $profile_image;
                $completeProfile['profile_image'] = $file_path;
            }

            if ($request->hasFile('cover_image')) {
                $cover_image = strtotime("now") . mt_rand(100000, 900000) . '.' . $request->cover_image->getClientOriginalExtension();
                $request->cover_image->move(public_path('/media/cover_image'), $cover_image);
                $file_path = '/media/cover_image/' . $cover_image;
                $completeProfile['cover_image'] = $file_path;
            }

            // For user
            if (isset($request->interest_id) && count($request->interest_id) > 0) {
                UserInterest::where('user_id', auth()->id())->delete();
                foreach ($request->interest_id as $interest_id) {
                    UserInterest::create([
                        'user_id'     =>   auth()->id(),
                        'interest_id' =>   $interest_id
                    ]);
                }
            }

            // This expertise id is Interest id for influencer
            if (isset($request->expertise) && count($request->expertise) > 0) {
                UserInterest::where('user_id', auth()->id())->delete();
                foreach ($request->expertise as $interest_id) {
                    UserInterest::create([
                        'user_id'     =>   auth()->id(),
                        'interest_id' =>   $interest_id
                    ]);
                }
            }

            $completeProfile['is_profile_complete'] = '1';
            $update_user = User::whereId($authId)->update($completeProfile);

            if ($update_user) {
                $user = User::find($authId);
                $userResource = new UserResource($user);

                if ($authUser->is_profile_complete == '0') {
                    $url = null;
                    if ($user->user_type == 'influencer') {
                        $accountLink = $paymentService->accountLink(auth()->user()->account_id);
                        $url = $accountLink->url;
                    }
                    return $this->successDataResponse('Profile completed successfully.', [
                        'url'   =>  $url,
                        'user'  =>  $userResource
                    ]);
                } else {
                    return $this->successDataResponse('Profile updated successfully.', $userResource);
                }
            } else {
                return $this->errorResponse('Something went wrong.', 400);
            }
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Push notification on off */
    public function pushNotificationOnOff()
    {
        $user = User::find(auth()->id());

        if ($user->push_notification == '0') {
            $user->push_notification = '1';
            $type = 'on';
        } else {
            $user->push_notification = '0';
            $type = 'off';
        }

        $user->save();
        return $this->successResponse('Push notification ' . $type . ' successfully.');
    }

    /** Logout */
    public function logout(Request $request)
    {
        $deleteTokens = $request->user()->currentAccessToken()->delete();

        if ($deleteTokens) {
            $user_type = auth()->user()->user_type;
            $update_user = User::whereId(auth()->user()->id)->update(['device_type' => null, 'device_token' => null]);
            if ($update_user) {
                return $this->successResponse(ucfirst($user_type) . ' logout successfully.');
            } else {
                return $this->errorResponse('Something went wrong.', 400);
            }
        } else {
            return $this->errorResponse('Something went wrong.', 400);
        }
    }

    /** Delete account */
    public function deleteAccount()
    {
        try {
            DB::beginTransaction();
            User::whereId(auth()->id())->update(['device_type' => null, 'device_token' => null]);
            $user = User::whereId(auth()->id())->first();
            $user->tokens()->delete();
            $user->delete();

            DB::commit();
            return $this->successResponse('Account has been deleted successfully.', 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Content */
    public function content(Request $request)
    {
        $this->validate($request, [
            'type' => 'required|exists:contents,type'
        ]);

        return $this->successDataResponse('Content found.', ['url' => url('content', $request->type)], 200);
    }
}
