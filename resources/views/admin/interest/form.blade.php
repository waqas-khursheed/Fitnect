@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="card">
    <div class="card-header">
        <h3 class="mb-0 card-title">{{ $title }}</h3>
    </div>
    <div class="card-body">
        <form action="{{ url('admin/interests/form') }}" method="post" enctype="multipart/form-data"> @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    @error('title') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <div class="form-group ">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" required>
                            <option value="1" selected>Active</option>
                            <option value="0">Deactive</option>
                        </select>
                    </div>
                    @error('status') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-12">
                    <button class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection