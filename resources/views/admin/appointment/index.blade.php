@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="row">
    <div class="col-md-12 col-lg-12">
        <div class="card">
            <div class="card-header">
            <a href="{{ url('admin/interests/form') }}" class="btn btn-success pull-right ">Add</a> &nbsp; &nbsp; &nbsp;
                <div class="card-title">{{ $title }}</div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="example1" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                @foreach($tableHeadings as $tableHeading)
                                <th class="wd-15p">{{ $tableHeading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @if(count($appointments) > 0)
                            @foreach($appointments as $appointment)
                            <tr>
                                <td> {{ $appointment->user->first_name }} {{ $appointment->user->last_name }} </td>
                                <td> {{ $appointment->influencer->first_name }} {{ $appointment->influencer->last_name }} </td>
                                <td> {{ $appointment->type }} </td>
                                <td> {{ $appointment->date }} </td>
                                <td> {{ $appointment->start_time }} </td>
                                <td> {{ $appointment->end_time }} </td>
                                <td> {{ $appointment->fee }} </td>
                                <td> {{ $appointment->platform_fee }} </td>
                                <td> {{ $appointment->merchant_fee }} </td>
                                <td> {{ $appointment->profit }} </td>
                                <td> {{ ucfirst($appointment->status) }} </td>
                                <td> {{ $appointment->created_at }} </td>
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection