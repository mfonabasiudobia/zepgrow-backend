@extends('layouts.main')

@section('title')
    {{ __('Edit User') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('customer.index') }}">{{ __('Users') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Edit User') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ __('User Details') }}</h4>
            </div>
            <div class="card-body">
                <form id="updateUserForm" class="form" action="{{ route('customer.update', $user->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="name">{{ __('Name') }} *</label>
                                <input type="text" id="name" class="form-control" name="name" 
                                    value="{{ $user->name }}" required>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="email">{{ __('Email') }} *</label>
                                <input type="email" id="email" class="form-control" name="email" 
                                    value="{{ $user->email }}" required>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="mobile">{{ __('Mobile') }} *</label>
                                <input type="text" id="mobile" class="form-control" name="mobile" 
                                    value="{{ $user->mobile }}" required>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="type">{{ __('Type') }} *</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="User" {{ $user->type === 'User' ? 'selected' : '' }}>{{ __('User') }}</option>
                                    <option value="Admin" {{ $user->type === 'Admin' ? 'selected' : '' }}>{{ __('Admin') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label for="address">{{ __('Address') }}</label>
                                <textarea class="form-control" id="address" name="address" 
                                    rows="3">{{ $user->address }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="password">{{ __('Password') }}</label>
                                <input type="password" id="password" class="form-control" name="password" 
                                    minlength="6">
                                <small class="text-muted">{{ __('Leave empty to keep current password') }}</small>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="password_confirmation">{{ __('Confirm Password') }}</label>
                                <input type="password" id="password_confirmation" class="form-control" 
                                    name="password_confirmation">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="status" 
                                        name="status" value="1" {{ $user->status ? 'checked' : '' }}>
                                    <label class="form-check-label" for="status">{{ __('Active') }}</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary me-1 mb-1">{{ __('Update') }}</button>
                            <a href="{{ route('customer.index') }}" class="btn btn-light-secondary mb-1">{{ __('Cancel') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@section('js')
<script>
    $(document).ready(function() {
        $('#updateUserForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var url = form.attr('action');
            
            $.ajax({
                type: "POST",
                url: url,
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(function() {
                            window.location.href = "{{ route('customer.index') }}";
                        }, 1500);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        var errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error('Error updating user');
                    }
                }
            });
        });
    });
</script>
@endsection