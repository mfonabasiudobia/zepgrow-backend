<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\Item;
use App\Models\ItemCustomFieldValue;
use App\Models\UserFcmToken;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\NotificationService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ItemController extends Controller {

    public function index() {
        ResponseService::noAnyPermissionThenRedirect(['item-list', 'item-update', 'item-delete']);
        $countries = Country::all();
        $cities = City::all();
        return view('items.index' ,compact('countries','cities'));
    }

    public function show($status , Request $request) {
        try {
            ResponseService::noPermissionThenSendJson('item-list');
            $offset = $request->input('offset', 0);
            $limit = $request->input('limit', 10);
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'ASC');
            $sql = Item::with(['custom_fields', 'category:id,name', 'user:id,name', 'gallery_images','featured_items'])->withTrashed();
            if (!empty($request->search)) {
                $sql = $sql->search($request->search);
            }




            if ($status == 'approved') {
                $sql->where('status', 'approved')->getNonExpiredItems()->whereNull('items.deleted_at');
            } elseif ($status == 'requested') {
                $sql->where('status', '!=', 'approved')
                    ->orWhere(function ($query) {
                        $query->where('status', 'approved')
                              ->whereNotNull('expiry_date')
                              ->where('expiry_date', '<', Carbon::now())
                              ->orWhereNotNull('items.deleted_at');
                    });
            }
            if (!empty($request->filter)) {
                $sql = $sql->filter(json_decode($request->filter, false, 512, JSON_THROW_ON_ERROR));
            }

            $total = $sql->count();
            $sql = $sql->sort($sort, $order)->skip($offset)->take($limit);
            $result = $sql->get();
            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();

            $itemCustomFieldValues = ItemCustomFieldValue::whereIn('item_id', $result->pluck('id'))->get();
            foreach ($result as $row) {
                /* Merged ItemCustomFieldValue's data to main data */
                $itemCustomFieldValue = $itemCustomFieldValues->filter(function ($data) use ($row) {
                    return $data->item_id == $row->id;
                });
                $featured_status = $row->featured_items->isNotEmpty() ? 'Featured' : 'Premium';
                $row->custom_fields = collect($row->custom_fields)->map(function ($customField) use ($itemCustomFieldValue) {
                    $customField['value'] = $itemCustomFieldValue->first(function ($data) use ($customField) {
                        return $data->custom_field_id == $customField->id;
                    });

                    if ($customField->type == "fileinput" && !empty($customField['value']->value)) {
                        if (!is_array($customField->value)) {
                            $customField['value'] = !empty($customField->value) ? [url(Storage::url($customField->value))] : [];
                        } else {
                            $customField['value'] = null;
                        }
//                        $customField['value']->value = url(Storage::url($customField['value']->value));
                    }
                    return $customField;
                });
                $tempRow = $row->toArray();
                $operate = '';
                if (count($row->custom_fields) > 0 && Auth::user()->can('item-list')) {
                    // View Custom Field
                    $operate .= BootstrapTableService::button('fa fa-eye', '#', ['editdata', 'btn-light-danger  '], ['title' => __("View"), "data-bs-target" => "#editModal", "data-bs-toggle" => "modal",]);
                }

                if ($row->status !== 'sold out' && Auth::user()->can('item-update')) {
                    $operate .= BootstrapTableService::editButton(route('advertisement.approval', $row->id), true, '#editStatusModal', 'edit-status', $row->id);
                }
                if (Auth::user()->can('item-delete')) {
                    $operate .= BootstrapTableService::deleteButton(route('advertisement.destroy', $row->id));
                }
                $tempRow['active_status'] = empty($row->deleted_at);//IF deleted_at is empty then status is true else false
                $tempRow['featured_status'] = $featured_status;
                $tempRow['operate'] = $operate;

                $rows[] = $tempRow;
            }
            $bulkData['rows'] = $rows;
            return response()->json($bulkData);

        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "ItemController --> show");
            ResponseService::errorResponse();
        }
    }
    public function updateItemApproval(Request $request, $id) {
        try {
            ResponseService::noPermissionThenSendJson('item-update');
            $item = Item::with('user')->withTrashed()->findOrFail($id);
            $item->update([
                ...$request->all(),
                'rejected_reason' => ($request->status == "soft rejected" || $request->status == "permanent rejected") ? $request->rejected_reason : ''
            ]);
            $user_token = UserFcmToken::where('user_id', $item->user->id)->pluck('fcm_token')->toArray();
            if (!empty($user_token)) {
                NotificationService::sendFcmNotification($user_token, 'About ' . $item->name, "Your Advertisement is " . ucfirst($request->status), "item-update", ['id' => $request->id,]);
            }
            ResponseService::successResponse('Advertisement Status Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'ItemController ->updateItemApproval');
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function destroy($id) {
        ResponseService::noPermissionThenSendJson('item-delete');

        try {
            $item = Item::with('gallery_images')->withTrashed()->findOrFail($id);
            foreach ($item->gallery_images as $gallery_image) {
                FileService::delete($gallery_image->getRawOriginal('image'));
            }
            FileService::delete($item->getRawOriginal('image'));

            $item->forceDelete();

            ResponseService::successResponse('Advertisement deleted successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse('Something went wrong');
        }
    }
    public function requestedItem() {
        ResponseService::noAnyPermissionThenRedirect(['item-list', 'item-update', 'item-delete']);
        $countries = Country::all();
        $cities = City::all();
        return view('items.requested_item' ,compact('countries','cities'));
    }
    public function searchCities(Request $request)
    {
        $countryName = trim($request->query('country_name'));
        if ($countryName == 'All') {
            return response()->json(['message' => 'Success', 'data' => []]);
        }
        $country = Country::where('name', $countryName)->first();
        if (!$country) {
            return response()->json(['message' => 'Success', 'data' => []]);
        }
        $cities = City::where('country_id', $country->id)->get();
        return response()->json(['message' => 'Success', 'data' => $cities]);
    }

}
