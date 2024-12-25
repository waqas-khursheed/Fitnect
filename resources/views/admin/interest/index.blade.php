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
                            @if(count($interests) > 0)
                            @foreach($interests as $interest)
                            <tr>
                                <td>
                                    {{ $interest->title }}
                                </td>
                                <td>
                                    <div class="material-switch">
                                        <input id="status{{ $interest->id }}" onclick="redirectToInterest({{ $interest->id }}, {{ $interest->status }})" name="status" type="checkbox" {{ $interest->status == 1 ? 'checked' : '' }}/>
                                        <label for="status{{ $interest->id }}" class="label-success"></label>
                                    </div>
                                </td>
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

<script>
    function redirectToInterest(id, $status) {
        window.location.href = "{{ url('admin/interests/status') }}" + "/" + id + "/" + $status; 
    }
</script>

@endsection