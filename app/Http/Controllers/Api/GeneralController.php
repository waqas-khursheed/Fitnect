<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\City;
use App\Models\Country;
use App\Models\Favourite;
use App\Models\HelpAndFeedBack;
use App\Models\Interest;
use App\Models\Notification;
use App\Models\Review;
use App\Models\State;
use App\Models\User;
use App\Services\PaymentService;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class GeneralController extends Controller
{
    use ApiResponser;

    /** Delete attachment */
    public static function deleteAttachment($deleted_ids)
    {
        try{
            DB::beginTransaction();
            $attachments = Attachment::whereIn('id', $deleted_ids)->get();

            if(count($attachments) > 0){
                foreach($attachments as $attachment){
                    if(file_exists(public_path($attachment->attachment))){
                        unlink(public_path($attachment->attachment));
                    }
                    $attachment->delete();
                }
            }

            DB::commit();
            return 1;
        } catch (\Exception $exception){
            DB::rollBack();
            return $exception->getMessage();
        }
    }

    /** List notification */
    public function notificationList(Request $request)
    {
        $this->validate($request, [
            'offset'       =>       'required|numeric'
        ]);

        $notifications = Notification::with('user:id,first_name,last_name,profile_image,user_type')->where('receiver_id', auth()->id());
        $notificationCount = $notifications->count();
        $notifications = $notifications->latest()->skip($request->offset)->take(10)->get();

        if(count($notifications) > 0){
            $data = [
                'total_notifications' => $notificationCount,
                'notifications'       => $notifications
            ];
            Notification::where(['receiver_id' => auth()->id(), 'read_at' => null, 'seen' => '0'])->update(['read_at' => now(), 'seen' => '1']);
            return $this->successDataResponse('Notification list found.', $data, 200);
        } else {
            return $this->errorResponse('Notification list not found.', 400);
        }
    }

    /** Unread notification count */
    public function notificationUnreadCount()
    {
        $count = Notification::where(['receiver_id' => auth()->id(), 'read_at' => null, 'seen' => '0'])->count();
        return $this->successDataResponse('Notification count.', ['notification_count' => $count]);
    }

    /** Delete notification */
    public function notificationDelete(Request $request)
    {
        $this->validate($request, [
            'notification_id'       =>       'required|exists:notifications,id'
        ]);

        try{
            DB::beginTransaction();
            Notification::whereId($request->notification_id)->delete();

            DB::commit();
            return $this->successResponse('Notification has been deleted successfully.');
        } catch (\Exception $exception){
            DB::rollBack();
            return $exception->getMessage();
        }
    }

    /** Get country */
    public function getCountry()
    {
        try {
            $countries = Country::select('id', 'sortname', 'name', 'phonecode')->get();
            return $this->successDataResponse('Countries list.', $countries);
        } catch (\Exception $exception){
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Get state */
    public function getState(Request $request)
    {
        $this->validate($request, [
            'country_id'   =>  'required|exists:countries,id',
        ]);

        try {
            $states = State::where('country_id', $request->country_id)->select('id', 'name')->get();
            return $this->successDataResponse('States list.', $states);
        } catch (\Exception $exception){
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Get city */
    public function getCity(Request $request)
    {
        $this->validate($request, [
            'state_id'   =>  'required|exists:states,id',
        ]);

        try {
            $cities = City::where('state_id', $request->state_id)->select('id', 'name')->get();
            return $this->successDataResponse('Cities list.', $cities);
        } catch (\Exception $exception){
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Interest list */
    public function interestList()
    {
        $interestList = Interest::active()->latest()->select('id', 'title')->get();
        return $this->successDataResponse('Interest list.', $interestList);
    }

    /** List favourite */
    public function favouriteList()
    {
        $favourite = Favourite::where(['type' => 'user', 'user_id' => auth()->id()])->get()->pluck('record_id');
        $data = User::whereIn('id', $favourite)
        ->select('id', 'first_name', 'last_name', 'profile_image', 'user_type', 'is_merchant_setup')
        ->withAvg('reviews as avg_rating', 'rating')
        ->latest()->get();

        if(count($data) > 0){
            return $this->successDataResponse('Favourite list found successfully.', $data, 200);
        } else {
            return $this->errorResponse('No favourite list found.', 400);
        }
    }

    /** Add or remove to favourite */
    public function addRemoveToFavourite(Request $request)
    {
        $this->validate($request, [
            'user_id'       =>  'required|exists:users,id'
        ]);

        try{
            $type = 'user';
            $record_id = $request->user_id;

            if($record_id == auth()->id()){
                return $this->errorResponse('Cannot add yourself to favourite.', 400);
            }

            $favouriteExists = Favourite::where(['user_id' => auth()->id(), 'record_id' => $record_id, 'type' => $type])->first();

            if(!empty($favouriteExists)){
                $favouriteExists->delete();
                return $this->successResponse('Removed from favorite success.');
            } else {
                Favourite::create([
                    'type'      => $type,
                    'record_id' => $record_id,
                    'user_id'   => auth()->id()
                ]);
                return $this->successResponse('Add to favorite success.');
            }
        } catch (\Exception $exception){
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** List review */
    public function reviewList(Request $request)
    {
        $this->validate($request, [
            'user_id'       =>  'required|exists:users,id'
        ]);

        $reviews = Review::with('sender')->where(['receiver_id' => $request->user_id])->latest()->get();
        
        if(count($reviews) > 0){
            return $this->successDataResponse('Review list found successfully.', $reviews, 200);
        } else {
            return $this->errorResponse('No review list found.', 400);
        }
    }

    /** Add review */
    public function addReview(Request $request)
    {
        $this->validate($request, [
            'user_id'       =>  'required|exists:users,id',
            'review'        =>  'required',
            'rating'        =>  'required|numeric|between:1,5'
        ]);
        
        try{
            if($request->user_id == auth()->id()){
                return $this->errorResponse('Cannot given review to yourself.', 400);
            }

            Review::create([
                'sender_id'        => auth()->id(),
                'receiver_id'      => $request->user_id,
                'review'           => $request->review,
                'rating'           => $request->rating
            ]);
            return $this->successResponse('Review has been given successfully.');
        } catch (\Exception $exception){
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    public function addCard (Request $request, PaymentService $paymentService) 
    {
        $this->validate($request, [
            'card_token'        =>  'required',
        ]);
        
        $user = User::select('id', 'customer_id')->find(auth()->id());
        
        $customerId = $user->customer_id;
        if ($customerId == null) {
            $user->customer_id = $paymentService->createCustomer($user->id)?->id;
            $user->save();

            $customerId = $user->customer_id;
        }

        $paymentService->assignCardToCustomer($customerId, $request->card_token);
        return $this->successResponse('Card added successfully.');
    }

    public function getCards(PaymentService $paymentService) {

        $user = User::select('id','customer_id')->find(auth()->id());
        $customerId = $user->customer_id;
        
        if ($customerId == null) {
            $user->customer_id = $paymentService->createCustomer($user->id)?->id;
            $user->save();

            $customerId = $user->customer_id;
        }

        return $this->successDataResponse("Cards list.", $paymentService->getAllCards($customerId));
    }

    public function makeCardDefault (Request $request, PaymentService $paymentService) 
    {
        $this->validate($request, [
            'card_id' => 'required'
        ]);
        $paymentService->setCardToDefault(auth()->user()->customer_id, $request->card_id);
        return $this->successResponse("Card set to default successfully.");
    }

    public function deleteCard (Request $request , PaymentService $paymentService) 
    {
        $this->validate($request, [
            'card_id' => 'required'
        ]);

        $paymentService->deleteCard(auth()->user()->customer_id, $request->card_id);
        return $this->successResponse("Card deleted successfully.");
    }

    /** Help and feedback */
    public function helpAndFeedback(Request $request)
    {
        $this->validate($request, [
            'subject'          =>      'required',
            'description'      =>      'required'
        ]);

        $images = array();

        if($request->has('images')){
            foreach($request->images as $image){
                $imageName = strtotime("now"). mt_rand(100000,900000) . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('/media/help_and_feedback'), $imageName);
                array_push($images, '/media/help_and_feedback/' . $imageName);
            }
        }

        $feedback = HelpAndFeedBack::create([
            'user_id'       =>  auth()->id(),
            'subject'       =>  $request->subject,
            'description'   =>  $request->description,
            'images'        =>  json_encode($images)
        ]);

        return $this->successResponse('Feedback has been submit successfully.');
    }
}
