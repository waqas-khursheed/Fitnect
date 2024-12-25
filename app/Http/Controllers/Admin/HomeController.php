<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Content;
use App\Models\HelpAndFeedBack;
use App\Models\Interest;
use App\Models\ProfileSticker;
use App\Models\User;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function dashboard()
    {
        $title = 'Dashboard';
        return view('admin.home.index', compact('title'));
    }

    public function helpAndFeedback()
    {
        $title = 'Help and FeedBack';
        $helpAndFeedbacks = HelpAndFeedBack::with('user')->latest()->get();
        $tableHeadings = ['First Name', 'Last Name', 'Type', 'Profile Image', 'Subject', 'description', 'Images', 'Created At'];
        return view('admin.help-and-feedback.index', compact('title', 'helpAndFeedbacks', 'tableHeadings'));
    }

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    public function usersList()
    {
        $title = 'Users';
        $users = User::where('user_type', '!=', 'admin')->otpVerified()->profileCompleted()->latest()->get();
        $tableHeadings = ['First Name', 'Last Name', 'Type', 'Profile Image', 'E-mail', 'Gender', 'Date of Birth', 'Login Type', 'Is Block'];
        return view('admin.users.index', compact('title', 'users', 'tableHeadings'));
    }

    public function userBlock($id, $is_block)
    {
        try{
            if($is_block == '1'){
                $is_block = '0';
                $messageTitle = 'Un Blocked';
            } else {
                $is_block = '1';
                $messageTitle = 'Blocked';
            }

            User::whereId($id)->update(['is_blocked' => $is_block]);
            return redirect('admin/users')->with('success', 'User has been ' . $messageTitle);
        } catch (\Exception $exception){
            return back()->with('error', $exception->getMessage());
        } 
    }

    /*
    |--------------------------------------------------------------------------
    | Interest
    |--------------------------------------------------------------------------
    */
    public function interestList()
    {
        $title = 'Interest';
        $interests = Interest::latest()->get();
        $tableHeadings = ['Title', 'Status'];
        return view('admin.interest.index', compact('title', 'interests', 'tableHeadings'));
    }

    public function interestForm()
    {
        $title = 'Interest';
        return view('admin.interest.form', compact('title'));
    }

    public function interestFormSubmit(Request $request)
    {
        $validatedData = $request->validate([
            'title'      => 'required',
            'status'     => 'required|in:0,1'
        ]);    

        try{
            $data  = $request->only('title', 'status');
            Interest::create($data);
            return redirect('admin/interests')->with('success', 'Interest has been saved.');
        } catch (\Exception $exception){
            return back()->with('error', $exception->getMessage());
        }
    }

    public function interestStatus($id, $status)
    {
        try{
            if($status == '1'){
                $status = '0';
            } else {
                $status = '1';
            }

            Interest::whereId($id)->update(['status' => $status]);
            return redirect('admin/interests')->with('success', 'Status has been update.');
        } catch (\Exception $exception){
            return back()->with('error', $exception->getMessage());
        } 
    }

    /*
    |--------------------------------------------------------------------------
    | Appointment
    |--------------------------------------------------------------------------
    */
    public function appointmentList()
    {
        $title = "Appointment";
        $appointments = Appointment::with('user', 'influencer')->latest()->get(); 
        $tableHeadings = ['User Full Name', 'Influencer Full Name', 'Type', 'Date', 'Start Time', 'End Time', 'Fee', 'Platform Fee', 'Merchant Fee', 'Profit', 'Status', 'Created At'];
        return view('admin.appointment.index', compact('title', 'appointments', 'tableHeadings'));
    }

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    */
    public function getContent($type)
    {
        if($type == 'pp'){
            $title = 'Privacy Policy';
        } else {
            $title = 'Terms and Conditions';
        }

        $content = Content::where('type', $type)->first();
        return view('admin.content.index', compact('title', 'content'));
    }

    public function updateContent(Request $request, $type)
    {
        try{
            $content = Content::where('type', $type)->update(['content' => $request->content]);
            return redirect('admin/content/'.$type)->with('success', 'Content has been update.');
        } catch (\Exception $exception){
            return back()->with('error', $exception->getMessage());
        } 
    }
}
