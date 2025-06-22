@extends('layouts.main')

@section('title')
    {{ __('Users') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table-borderless table-striped" aria-describedby="mydesc" id="table_list"
                               data-toggle="table" data-url="{{ route('customer.show',1) }}" data-click-to-select="true"
                               data-side-pagination="server" data-pagination="true"
                               data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                               data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                               data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                               data-responsive="true" data-sort-name="id" data-sort-order="desc"
                               data-escape="true"
                               data-pagination-successively-size="3" data-query-params="queryParams" data-table="users" data-status-column="deleted_at"
                               data-show-export="true" data-export-options='{"fileName": "customer-list","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                               data-mobile-responsive="true">
                            <thead class="thead-dark">
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                <th scope="col" data-field="profile" data-formatter="imageFormatter">{{ __('Profile') }}</th>
                                <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                <th scope="col" data-field="email" data-sortable="true">{{ __('Email') }}</th>
                                <th scope="col" data-field="mobile" data-sortable="true">{{ __('Mobile') }}</th>
                                <th scope="col" data-field="type" data-sortable="true">{{ __('Type') }}</th>
                                <th scope="col" data-field="address" data-sortable="true">{{ __('Address') }}</th>
                                <th scope="col" data-field="items_count" data-sortable="true">{{ __('Total Post') }}</th>
                                <th scope="col" data-field="status" data-formatter="statusSwitchFormatter" data-sortable="false">{{ __('Status') }}</th>                                <th scope="col" data-field="auto_approve_advertisement" data-formatter="autoApproveItemSwitchFormatter" data-sortable="false">{{ __('Auto Approve Advertisement') }}</th>
                                <th scope="col" data-field="operate" data-formatter="operateFormatter" data-escape="false" data-align="center" data-sortable="false" data-events="userEvents">{{ __('Action') }}</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div id="assignPackageModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('Assign Packages') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="resetModal()"></button>
                    </div>
                    <div class="modal-body">
                        <form class="create-form" action="{{ route('customer.assign.package') }}" method="POST" data-parsley-validate data-success-function="assignApprovalSuccess">
                            @csrf
                            <input type="hidden" name="user_id" id='user_id'>
                            <div id="currency-settings" data-symbol="{{ $currency_symbol }}"  data-position="{{ $currency_symbol_position }}" data-free-ad-listing="{{ $free_ad_listing }}"></div>
                            @if($free_ad_listing != 1)
                            <div class="form-group row select-package">
                                <div class="col-md-6">
                                    <input type="radio" id="item_package" class="package_type form-check-input" name="package_type" value="item_listing" required>
                                    <label for="item_package">{{ __('Item Listing Package') }}</label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" id="advertisement_package" class="package_type form-check-input" name="package_type" value="advertisement" required>
                                    <label for="advertisement_package">{{ __('Advertisement Package') }}</label>
                                </div>
                            </div>
                            @endif
                            <div class="row mt-3" id="item-listing-package-div" style="display: none;">
                                <div class="form-group col-md-12">
                                    <label for="package">{{__("Select Item Listing Package")}}</label>
                                    <select name="package_id" class="form-select package" id="item-listing-package" aria-label="Package">
                                        <option value="" disabled selected>Select Option</option>
                                        @foreach($itemListingPackage as $package)
                                            <option value="{{$package->id}}" data-details="{{json_encode($package)}}">{{$package->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3" id="advertisement-package-div" style="{{ $free_ad_listing == '1' ? 'display: block;' : 'display: none;' }}">
                                <div class="form-group col-md-12">
                                    <label for="package">{{__("Select Advertisement Package")}}</label>
                                    <select name="package_id" class="form-select package" id="advertisement-package" aria-label="Package">
                                        <option value="" disabled selected>Select Option</option>
                                        @foreach($advertisementPackage as $package)
                                            <option value="{{$package->id}}" data-details="{{json_encode($package)}}">{{$package->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div id="package_details" class="mt-3" style="display: none;">
                                <p><strong>Name:</strong> <span id="package_name"></span></p>
                                <p><strong>Price:</strong> <span id="package_price"></span></p>
                                <p><strong>Final Price:</strong> <span id="package_final_price"></span></p>
                                <p><strong>Limitation:</strong> <span id="package_duration"></span></p>
                            </div>
                            <div class="form-group row payment" style="display: none">
                                <div class="col-md-6">
                                    <input type="radio" id="cash_payment" class="payment_gateway form-check-input" name="payment_gateway" value="cash" required>
                                    <label for="cash_payment">{{ __('Cash') }}</label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" id="cheque_payment" class="payment_gateway form-check-input" name="payment_gateway" value="cheque" required>
                                    <label for="cheque_payment">{{ __('Cheque') }}</label>
                                </div>
                            </div>
                            <div class="form-group cheque mt-3" style="display: none">
                                <label for="cheque">{{ __('Add cheque number') }}</label>
                                <input type="text" id="cheque" class="form-control" name="cheque_number" data-parsley-required="true">
                            </div>
                            <input type="submit" value="{{__("Save")}}" class="btn btn-primary mt-3">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('js')
    <script>
        function assignApprovalSuccess() {
            $('#assignPackageModal').modal('hide');
        }
        function resetModal() {
            const modal = $('#assignPackageModal');
            const form = modal.find('form');
            form[0].reset();
        }        // Define user events for the table actions
        window.userEvents = {
            'click .assign-package': function (e, value, row, index) {
                $('#user_id').val(row.id);
                $('#assignPackageModal').modal('show');
            },
            'click .edit-user': function (e, value, row, index) {
                window.location.href = `/customer/${row.id}/edit`;
            },
            'click .delete-user': function (e, value, row, index) {
                if (confirm('Are you sure you want to delete this user?')) {
                    $.ajax({
                        url: `/customer/${row.id}`,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(result) {
                            $('#table_list').bootstrapTable('refresh');
                            toastr.success('User deleted successfully');
                        },
                        error: function(xhr, status, error) {
                            toastr.error('Error deleting user');
                        }
                    });
                }
            }
        };        // Function to format action buttons
        function operateFormatter(value, row, index) {
            return [
                '<div class="btn-group" role="group">',
                    '<button type="button" class="btn btn-sm btn-info assign-package" title="Assign Package">',
                        '<i class="bi bi-cart"></i>',
                    '</button>',
                    '<button type="button" class="btn btn-sm btn-primary edit-user" title="Edit">',
                        '<i class="bi bi-pencil"></i>',
                    '</button>',
                    '<button type="button" class="btn btn-sm btn-danger delete-user" title="Delete">',
                        '<i class="bi bi-trash"></i>',
                    '</button>',
                '</div>'
            ].join('');
        }
    </script>
@endsection
