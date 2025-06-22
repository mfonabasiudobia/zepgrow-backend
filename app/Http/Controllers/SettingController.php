<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfiguration;
use App\Models\Setting;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SettingController extends Controller {
    private string $uploadFolder;

    public function __construct() {
        $this->uploadFolder = 'settings';
    }

    public function index() {
        ResponseService::noPermissionThenRedirect('settings-update');
        return view('settings.index');
    }

    public function page() {
        ResponseService::noPermissionThenSendJson('settings-update');
        $type = last(request()->segments());
        $settings = CachingService::getSystemSettings()->toArray();
        if (!empty($settings['place_api_key']) && config('app.demo_mode')) {
            $settings['place_api_key'] = "**************************";
        }
        $stripe_currencies = ["USD", "AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD", "AWG", "AZN", "BAM", "BBD", "BDT", "BGN", "BIF", "BMD", "BND", "BOB", "BRL", "BSD", "BWP", "BYN", "BZD", "CAD", "CDF", "CHF", "CLP", "CNY", "COP", "CRC", "CVE", "CZK", "DJF", "DKK", "DOP", "DZD", "EGP", "ETB", "EUR", "FJD", "FKP", "GBP", "GEL", "GIP", "GMD", "GNF", "GTQ", "GYD", "HKD", "HNL", "HTG", "HUF", "IDR", "ILS", "INR", "ISK", "JMD", "JPY", "KES", "KGS", "KHR", "KMF", "KRW", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT", "MOP", "MRO", "MUR", "MVR", "MWK", "MXN", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR", "NZD", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR", "RON", "RSD", "RUB", "RWF", "SAR", "SBD", "SCR", "SEK", "SGD", "SHP", "SLE", "SOS", "SRD", "STD", "SZL", "THB", "TJS", "TOP", "TTD", "TWD", "TZS", "UAH", "UGX", "UYU", "UZS", "VND", "VUV", "WST", "XAF", "XCD", "XOF", "XPF", "YER", "ZAR", "ZMW"];
        $languages = CachingService::getLanguages();
        return view('settings.' . $type, compact('settings', 'type', 'languages', 'stripe_currencies'));
    }

    public function store(Request $request) {
        ResponseService::noPermissionThenSendJson('settings-update');

        $validator = Validator::make($request->all(), [
            "company_name"           => "nullable",
            "company_email"          => "nullable",
            "company_tel1"           => "nullable",
            "company_tel2"           => "nullable",
            "company_address"        => "nullable",
            "default_language"       => "nullable",
            "currency_symbol"        => "nullable",
            "android_version"        => "nullable",
            "play_store_link"        => "nullable",
            "ios_version"            => "nullable",
            "app_store_link"         => "nullable",
            "maintenance_mode"       => "nullable",
            "force_update"           => "nullable",
            "number_with_suffix"     => "nullable",
            "firebase_project_id"    => "nullable",
            "service_file"           => "nullable",
            "favicon_icon"           => "nullable|mimes:jpg,jpeg,png,svg|max:7168",
            "company_logo"           => "nullable|mimes:jpg,jpeg,png,svg|max:7168",
            "login_image"            => "nullable|mimes:jpg,jpeg,png,svg|max:7168",
            // "watermark_image"        => 'nullable|mimes:jpg,jpeg,png|max:7168',
            "web_theme_color"        => "nullable",
            "place_api_key"          => "nullable",
            "header_logo"            => "nullable|mimes:jpg,jpeg,png,svg|max:7168",
            "footer_logo"            => "nullable|mimes:jpg,jpeg,png,svg|max:7168",
            "placeholder_image"      => "nullable|mimes:jpg,jpeg,png,svg|max:7168",
            "footer_description"     => "nullable",
            "google_map_iframe_link" => "nullable",
            "default_latitude"       => "nullable",
            "default_longitude"      => "nullable",
            "instagram_link"         => "nullable|url",
            "x_link"                 => "nullable|url",
            "facebook_link"          => "nullable|url",
            "linkedin_link"          => "nullable|url",
            "pinterest_link"         => "nullable|url",
            "deep_link_text_file"    => "nullable",
            "deep_link_json_file"    => "nullable|mimes:json|max:7168",
            "mobile_authentication"    => "nullable",
            "google_authentication"    => "nullable",
            "email_authentication"    => "nullable",
            "apple_authenticaion"    =>"nullable",
            // Email settings validation
            "mail_mailer"            => "nullable",
            "mail_host"              => "nullable",
            "mail_port"              => "nullable",
            "mail_username"          => "nullable",
            "mail_password"          => "nullable",
            "mail_encryption"        => "nullable",
            "mail_from_address"      => "nullable|email",
            'deep_link_scheme'       => 'nullable|string|regex:/^[a-z][a-z0-9]*$/|max:30',
            "otp_service_provider"    => "nullable|in:firebase,twilio",
            "twilio_account_sid"      => "nullable",
            "twilio_auth_token"       => "nullable",
            "twilio_my_phone_number"  => "nullable",
        ]);
        if (
            $request->has('mobile_authentication') && $request->mobile_authentication == 0 &&
            $request->has('google_authentication') && $request->google_authentication == 0 &&
            $request->has('email_authentication') && $request->email_authentication == 0 &&
            $request->has('apple_authentication') && $request->apple_authentication == 0
        ) {
             ResponseService::validationError('At least one authentication method must be enabled.');
        }
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {

            $inputs = $request->input();

            unset($inputs['_token']);
            if (config('app.demo_mode')) {
                unset($inputs['place_api_key']);
            }
            $data = [];
            foreach ($inputs as $key => $input) {
                $data[] = [
                    'name'  => $key,
                    'value' => $input,
                    'type'  => 'string'
                ];
            }

            //Fetch old images to delete from the disk storage
            $oldSettingFiles = Setting::whereIn('name', collect($request->files)->keys())->get();
            foreach ($request->files as $key => $file) {

                if (in_array($key, ['deep_link_json_file', 'deep_link_text_file'])) {
                    $filenameMap = [
                        'deep_link_json_file' => 'assetlinks.json',
                        'deep_link_text_file' => 'apple-app-site-association',
                    ];

                    $filename = $filenameMap[$key];
                    $fileContents = File::get($file);
                    $publicWellKnownPath = public_path('.well-known');
                    if (!File::exists($publicWellKnownPath)) {
                        File::makeDirectory($publicWellKnownPath, 0755, true);
                    }

                    $publicPath = public_path('.well-known/' . $filename);
                    File::put($publicPath, $fileContents);

                    $rootPath = base_path('.well-known/' . $filename);
                    File::put($rootPath, $fileContents);
                } else {

                    $data[] = [
                        'name'  => $key,
                        'value' =>FileService::compressAndUpload($request->file($key),$this->uploadFolder),
                        // 'value' => $request->file($key)->store($this->uploadFolder, 'public'),
                        'type'  => 'file'
                    ];
                    $oldFile = $oldSettingFiles->first(function ($old) use ($key) {
                        return $old->name == $key;
                    });
                    if (!empty($oldFile)) {
                        FileService::delete($oldFile->getRawOriginal('value'));
                    }
                }
            }
            Setting::upsert($data, 'name', ['value']);

            if (!empty($inputs['company_name']) && config('app.name') != $inputs['company_name']) {
                HelperService::changeEnv([
                    'APP_NAME' => $inputs['company_name'],
                ]);
            }

            // Update .env file for email settings
            $emailSettings = [
                'MAIL_MAILER' => $inputs['mail_mailer'] ?? config('mail.mailer'),
                'MAIL_HOST' => $inputs['mail_host'] ?? config('mail.host'),
                'MAIL_PORT' => $inputs['mail_port'] ?? config('mail.port'),
                'MAIL_USERNAME' => $inputs['mail_username'] ?? config('mail.username'),
                'MAIL_PASSWORD' => $inputs['mail_password'] ?? config('mail.password'),
                'MAIL_ENCRYPTION' => $inputs['mail_encryption'] ?? config('mail.encryption'),
                'MAIL_FROM_ADDRESS' => $inputs['mail_from_address'] ?? config('mail.from.address'),
            ];
            HelperService::changeEnv($emailSettings);

            if (!empty($inputs['otp_service_provider']) && $inputs['otp_service_provider'] === 'twilio') {
                HelperService::changeEnv([
                    'TWILIO_ACCOUNT_SID'   => $inputs['twilio_account_sid'] ?? config('services.twilio.account_sid'),
                    'TWILIO_AUTH_TOKEN'    => $inputs['twilio_auth_token'] ?? config('services.twilio.auth_token'),
                ]);
            }

            CachingService::removeCache(config('constants.CACHE.SETTINGS'));
            ResponseService::successResponse('Settings Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "Setting Controller -> store");
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function updateFirebaseSettings(Request $request) {
        ResponseService::noPermissionThenSendJson('settings-update');
        $validator = Validator::make($request->all(), [
            'apiKey'            => 'required',
            'authDomain'        => 'required',
            'projectId'         => 'required',
            'storageBucket'     => 'required',
            'messagingSenderId' => 'required',
            'appId'             => 'required',
            'measurementId'     => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $inputs = $request->input();
            unset($inputs['_token']);
            $data = [];
            foreach ($inputs as $key => $input) {
                $data[] = [
                    'name'  => $key,
                    'value' => $input,
                    'type'  => 'string'
                ];
            }
            Setting::upsert($data, 'name', ['value']);
            //Service worker file will be copied here
            File::copy(public_path('assets/dummy-firebase-messaging-sw.js'), public_path('firebase-messaging-sw.js'));
            $serviceWorkerFile = file_get_contents(public_path('firebase-messaging-sw.js'));

            $updateFileStrings = [
                "apiKeyValue"            => '"' . $request->apiKey . '"',
                "authDomainValue"        => '"' . $request->authDomain . '"',
                "projectIdValue"         => '"' . $request->projectId . '"',
                "storageBucketValue"     => '"' . $request->storageBucket . '"',
                "messagingSenderIdValue" => '"' . $request->measurementId . '"',
                "appIdValue"             => '"' . $request->appId . '"',
                "measurementIdValue"     => '"' . $request->measurementId . '"'
            ];
            $serviceWorkerFile = str_replace(array_keys($updateFileStrings), $updateFileStrings, $serviceWorkerFile);
            file_put_contents(public_path('firebase-messaging-sw.js'), $serviceWorkerFile);
            CachingService::removeCache(config('constants.CACHE.SETTINGS'));
            ResponseService::successResponse('Settings Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "Settings Controller -> updateFirebaseSettings");
            ResponseService::errorResponse();
        }
    }

    public function paymentSettingsIndex() {
        ResponseService::noPermissionThenRedirect('settings-update');
        $paymentConfiguration = PaymentConfiguration::all();
        $paymentGateway = [];
        foreach ($paymentConfiguration as $row) {
            $paymentGateway[$row->payment_method] = $row->toArray();
        }
        $settings = CachingService::getSystemSettings()->toArray();
        return view('settings.payment-gateway', compact('paymentGateway','settings'));
    }

    public function paymentSettingsStore(Request $request) {
        ResponseService::noPermissionThenSendJson('settings-update');
        $validator = Validator::make($request->all(), [
            'gateway'          => 'required|array',
            'gateway.Stripe'   => 'required|array|required_array_keys:api_key,secret_key,webhook_secret_key,status',
            'gateway.Razorpay' => 'required|array|required_array_keys:api_key,secret_key,webhook_secret_key,status',
            'gateway.Paystack' => 'required|array|required_array_keys:api_key,secret_key,status',
            'gateway.PhonePe' => 'required|array|required_array_keys:secret_key,status',
            'bank'             => 'required|array'
        ]);
        $gatewayStatuses = [
            $request->input('gateway.Stripe.status',0),
            $request->input('gateway.Razorpay.status',0),
            $request->input('gateway.Paystack.status',0),
            $request->input('gateway.PhonePe.status',0),
            $request->input('gateway.flutterwave.status',0),
            $request->input('bank.bank_transfer_status',0),
        ];
        if (!in_array('1', $gatewayStatuses, true)) {
            ResponseService::validationError('At least one payment gateway must be enabled.');
        }
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {

            foreach ($request->input('bank') as $key => $value) {
                Setting::updateOrCreate(['name' => $key], ['value' => $value]);
            }
            foreach ($request->gateway as $key => $gateway) {
                PaymentConfiguration::updateOrCreate(['payment_method' => $key], [
                    'api_key'            => $gateway["api_key"] ?? '',
                    'secret_key'         => $gateway["secret_key"] ?? '',
                    'webhook_secret_key' => $gateway["webhook_secret_key"] ?? '',
                    'status'             => $gateway["status"] ?? '',
                    'currency_code'      => $gateway["currency_code"] ?? ''
                ]);
                if ($key === 'Paystack') {
                    HelperService::changeEnv([
                        'PAYSTACK_PUBLIC_KEY'  => $gateway['api_key'] ?? '',
                        'PAYSTACK_SECRET_KEY'  => $gateway['secret_key'] ?? '',
                        'PAYSTACK_PAYMENT_URL' => "https://api.paystack.co"
                    ]);
                }
            }


            ResponseService::successResponse('Settings Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "Settings Controller -> updateFirebaseSettings");
            ResponseService::errorResponse();
        }
    }
    // public function syatemStatusIndex() {
    //     return view('settings.system-status');
    // }
    public function toggleStorageLink()
    {
        $linkPath = public_path('storage');

        if (file_exists($linkPath)) {
            if (is_link($linkPath)) {
                if (unlink($linkPath)) {
                    return back()->with('message', 'Storage link unlinked successfully!');
                }

                return back()->with('message', 'Failed to unlink the storage link.');
            }

            return back()->with('message', 'Storage link is not a symbolic link.');
        } else {
            Artisan::call('storage:link');

            if (file_exists($linkPath) && is_link($linkPath)) {
                return back()->with('message', 'Storage link created successfully!');
            }

            return back()->with('message', 'Failed to create the storage link.');
        }
    }


    public function systemStatus() {
        $linkPath = public_path('storage');
        $isLinked = file_exists($linkPath) && is_dir($linkPath);

        return view('settings.system-status', compact('isLinked'));
    }

    public function fileManagerSettingStore(Request $request) {
        ResponseService::noPermissionThenSendJson('settings-update');
        $validator = Validator::make($request->all(), [
            "file_manager"    => "required|in:public,s3",
            "S3_aws_access_key_id"    => "required_if:file_manager,==,s3",
            "s3_aws_secret_access_key"    => "required_if:file_manager,==,s3",
            "s3_aws_default_region"    => "required_if:file_manager,==,s3",
            "s3_aws_bucket"    => "required_if:file_manager,==,s3",
            "s3_aws_url"    => "required_if:file_manager,==,s3",
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $inputs = $request->input();
            $data = [];
            foreach ($inputs as $key => $input) {
                $data[] = [
                    'name'  => $key,
                    'value' => $input,
                    'type'  => 'string'
                ];
            }
            Setting::upsert($data, 'name', ['value']);

            $env = [
                'FILESYSTEM_DISK' => $inputs['file_manager'],
                'AWS_ACCESS_KEY_ID' => $inputs['S3_aws_access_key_id'] ?? null,
                'AWS_SECRET_ACCESS_KEY' => $inputs['s3_aws_secret_access_key'] ?? null,
                'AWS_DEFAULT_REGION' => $inputs['s3_aws_default_region'] ?? null,
                'AWS_BUCKET' => $inputs['s3_aws_bucket'] ?? null,
                'AWS_URL' => $inputs['s3_aws_url'] ?? null,
            ];

            HelperService::changeEnv($env);
            ResponseService::successResponse('File Manager Settings Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "Setting Controller -> fileManagerSettingStore");
            ResponseService::errorResponse('Something Went Wrong');
        }
    }
    public function paystackPaymentSucesss(){
        return view('payment.paystack');
    }
    public function phonepePaymentSucesss(){
        return view('payment.phonepe');
    }
    public function webPageURL($slug){
        $appStoreLink = CachingService::getSystemSettings('app_store_link');
        $playStoreLink = CachingService::getSystemSettings('play_store_link');
        $appName = CachingService::getSystemSettings('company_name');
        $scheme = CachingService::getSystemSettings('deep_link_scheme');
        return view('deep-link.deep_link',compact('appStoreLink','playStoreLink','appName','scheme'));
    }

    public function flutterWavePaymentSucesss(){
        return view('payment.flutterwave');
    }
}
