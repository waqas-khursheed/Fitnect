<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\TokenService;
use App\Models\{
    User,
    ConferenceCall,
    ConferenceCallUser,
    Notification,
    Appointment
};

class RemoteMediaController extends Controller
{
    use ApiResponser;
    protected $tokenService;  public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function start_conference_call(Request $request)
    {

        $this->validate($request, [
            'appointment_id' => 'required|exists:appointments,id',
            "user_id"  => "required|exists:users,id",
        ]);


        $userId = (string)$request->input('user_id'); // Convert to string
        $appointmentId = $request->input('appointment_id');


        DB::beginTransaction();

        $authUser = auth()->user();
        $authId = $authUser->id;

        try {
            $conference_call = ConferenceCall::where('appointment_id', $appointmentId)
                ->where('user_id', $authId)->where('status', 'complete')
                ->first();

            if ($conference_call) {
                return $this->errorResponse('Appointment already completed', 400);
            }

       
            $ms100_room_creation_response = Http::withBody(json_encode([
                "name" => 'firnect-' . $this->gen_uuid()
            ]))
                ->withToken($this->tokenService->getManagementToken())
                ->post(config('app.100ms_endpoint') . 'rooms');

            $ms100_room_creation_response  = $ms100_room_creation_response->json();

            $conference_call = ConferenceCall::where('appointment_id', $appointmentId)
                ->where('user_id', $authId)
                ->first();

            if ($conference_call) {
                $conference_call->room_id = $ms100_room_creation_response['id'];
                $conference_call->save();
            } else {
                // Create a new conference call
                $conference_call = ConferenceCall::create([
                    'title' => "Appoiment Call",
                    'appointment_id' => $appointmentId,
                    'user_id' => $authId,
                    'room_id' => $ms100_room_creation_response['id'],
                    'status' => 'start',
                ]);
            }

            ConferenceCallUser::updateOrCreate(
                ['conference_call_id' => $conference_call->id, 'user_id' => $authId],
                ['token' => null]
            );

            $users_token = [];

            $issuedAt  = new \DateTimeImmutable();
            $expire    = $issuedAt->modify('+24 hours')->getTimestamp();

            $payload = [
                'iat'  => $issuedAt->getTimestamp(),
                'nbf'  => $issuedAt->getTimestamp(),
                'exp'  => $expire,
                'access_key' => config('app.100ms_app_access_key'),
                'type' => "app",
                'jti' =>  Uuid::uuid4()->toString(),
                'version' => 2,
                'role' => 'guest',
                'room_id' => $ms100_room_creation_response['id'],
                'user_id' => (string)$userId // Ensure user_id is a string
            ];

            $token = JWT::encode(
                $payload,
                config('app.100ms_app_secret_key'),
                'HS256'
            );


            // 
            $payload = [
                'iat'  => $issuedAt->getTimestamp(),
                'nbf'  => $issuedAt->getTimestamp(),
                'exp'  => $expire,
                'access_key' => config('app.100ms_app_access_key'),
                'type' => "app",
                'jti' =>  Uuid::uuid4()->toString(),
                'version' => 2,
                'role' => 'host',
                'room_id' => $ms100_room_creation_response['id'],
                'user_id' => (string)$userId // Ensure user_id is a string
            ];

            $token2 = JWT::encode(
                $payload,
                config('app.100ms_app_secret_key'),
                'HS256'
            );

            ConferenceCallUser::updateOrCreate(
                ['conference_call_id' => $conference_call->id, 'user_id' => $userId],
                ['token' => $token]
            );

            // Old notification delete first this Appointment
            Notification::where('record_id', $conference_call->id)->where('type', 'appointment_call')->delete();

            $reciever = User::find($userId);

            if ($reciever->push_notification == '1') {
                push_notification([
                    'device_token' => $reciever->device_token,
                    'title' => 'Appointment call started by ' . $authUser->first_name . ' ' . $authUser->last_name,
                    'description' => 'Appointment session has been started please join it asap.',
                    'type' => "appointment_call",
                    'record_id' => $appointmentId,
                    'sender_id' => $authId,
                    'receiver_id' => $userId,
                    'token' => $token,
                    'room_id' => $conference_call->room_id,

                    'first_name' => $authUser->first_name,
                    'last_name' => $authUser->last_name,
                    'profile_image' => $authUser->profile_image,
                ]);
            }

            in_app_notification([
                'sender_id' => $authId,
                'receiver_id' => $userId,
                'title' => 'Appointment call started by ' . $authUser->first_name . ' ' . $authUser->last_name,
                'description' => 'Appointment session has been started please join it asap.',
                'record_id' => $appointmentId,
                'type' => "appointment_call",
                'token' => $token,
                'room_id' => $conference_call->room_id,
            ]);

            $users_token[] = ['user_id' => $userId, 'token' => $token2];

            DB::commit();

            return $this->successDataResponse(
                "Conference room Created Successfully",
                array_merge(['conference_call_id' => $conference_call->id], $ms100_room_creation_response, ['users_token' => $users_token]),
                200
            );
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse($e->getMessage(), 400);
        }
    }



    public function end_conference_call(Request $request)
    {
        try {
            $conference_call_id = $request->conference_call_id;

            $conference_call = ConferenceCall::findOrFail($conference_call_id);

            if ($conference_call->ended_at) {
                throw new \Exception('This room seasion already has been ended');
            }

            $conference_call->ended_at = date('Y-m-d H:i:s');
            $conference_call->save();

            $conference_call->refresh();

            return $this->successResponse(
                "Appointment ended Successfully",
                200
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    private function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
