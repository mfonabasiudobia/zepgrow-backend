<?php

namespace App\Services;

use App\Models\Item;
use App\Models\UserPurchasedPackage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExpiringItemService
{
    /**
     * Send notifications for expiring items and packages.
     */
    public function notifyExpiringItemsAndPackages()
    {
        // ✅ Notify expiring items
        $this->notifyExpiringItems();

        // ✅ Notify expiring packages
        $this->notifyExpiringPackages();
    }

    /**
     * Send notifications for expiring items.
     */
    public function notifyExpiringItems()
    {
        $twoDaysFromNow = Carbon::now()->addDays(2)->startOfDay();
        $items = Item::whereDate('expiry_date', '=', $twoDaysFromNow)
            ->where('status', 'approved')  // Only get approved items
            ->get();
        $skippedCount = 0;

        foreach ($items as $item) {
            $user = $item->user;
            if ($user && $user->email) {
                $this->sendNotification($user, $item);
            } else {
                $skippedCount++;
            }
        }

        if ($skippedCount > 0) {
            Log::warning("Skipped {$skippedCount} item notifications due to missing user or email");
        }
    }

    /**
     * Send notifications for expiring packages.
     */
    public function notifyExpiringPackages()
    {
        $twoDaysFromNow = Carbon::now()->addDays(2)->startOfDay();
        $packages = UserPurchasedPackage::whereDate('end_date', '=', $twoDaysFromNow)->get();
        $skippedCount = 0;

        foreach ($packages as $package) {
            $user = $package->user;
            $packageDetails = $package->package;

            if ($user && $user->email && $packageDetails) {
                $this->sendPackageNotification($user, $packageDetails, $package);
            } else {
                $skippedCount++;
            }
        }

        if ($skippedCount > 0) {
            Log::warning("Skipped {$skippedCount} package notifications due to missing user, email, or package details");
        }
    }

    /**
     * Send email notification for expiring items.
     */
    protected function sendNotification(User $user, $item)
    {
        try {
            Mail::raw(
                "Hello {$user->name},\n\n" .
                "Your Advertisement '{$item->name}' is expiring on " . Carbon::parse($item->expiry_date)->format('d M Y') . ".\n" .
                "Please take the necessary action before it expires.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->from('admin@yourdomain.com', 'Admin')
                        ->subject('Advertisement Expiring Soon');
                }
            );

            Log::info("Expiry notification sent to: {$user->email} for Advertisement: {$item->name}");

        } catch (\Exception $e) {
            Log::error("Failed to send notification for Advertisement {$item->id}: " . $e->getMessage());
        }
    }

    /**
     * Send email notification for expiring packages.
     */
    protected function sendPackageNotification(User $user, $package, $userPackage)
    {
        try {
            Mail::raw(
                "Hello {$user->name},\n\n" .
                "Your subscription package '{$package->name}' is expiring on " . Carbon::parse($userPackage->end_date)->format('d M Y') . ".\n" .
                "Please renew or upgrade your subscription before it expires.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->from('admin@yourdomain.com', 'Admin')
                        ->subject('Package Expiring Soon');
                }
            );

            Log::info("Package expiry notification sent to: {$user->email} for package: {$package->name}");

        } catch (\Exception $e) {
            Log::error("Failed to send notification for Package {$userPackage->id}: " . $e->getMessage());
        }
    }
}
