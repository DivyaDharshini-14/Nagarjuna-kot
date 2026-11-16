<?php

namespace App\Listeners;

use App\Events\KotTicketChanged;
use App\Models\KotTicket;
use App\Models\MobileDeviceToken;
use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendKotPushNotification implements ShouldQueue
{
    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120];

    public bool $afterCommit = true;

    public function handle(KotTicketChanged $event, FcmPushService $push): void
    {
        $ticket = KotTicket::query()
            ->with(['kitchen', 'order.table', 'order.waiter'])
            ->find($event->ticketId);
        if (! $ticket || ! $ticket->order) return;

        if (in_array($event->changeType, ['created', 'cancelled'], true)) {
            $kitchenUsers = User::query()
                ->whereHas('kitchens', fn ($query) => $query
                    ->whereKey($ticket->kitchen_id)
                    ->where('kitchens.company_id', $ticket->company_id)
                    ->where('kitchens.branch_id', $ticket->branch_id)
                    ->where('kitchens.is_active', true))
                ->get()
                ->filter(fn (User $user) => $user->canAuthenticate()
                    && $user->isKitchenStaff()
                    && $user->hasPermission('kot.view'));

            $kitchenNotification = $event->changeType === 'created'
                ? ['title' => 'New kitchen ticket', 'body' => $ticket->kot_number.' · Table '.($ticket->order->table?->name ?? '—')]
                : ['title' => 'KOT cancellation', 'body' => $ticket->kot_number.' · Order '.$ticket->order->order_number];
            $this->sendToUsers($push, $kitchenUsers->modelKeys(), 'kitchen', $kitchenNotification, [
                'type' => $event->changeType === 'created' ? 'kot_created' : 'kot_cancelled',
                'ticket_id' => $ticket->id,
                'kitchen_id' => $ticket->kitchen_id,
                'order_id' => $ticket->order_id,
                'order_number' => $ticket->order->order_number,
                'notification_id' => 'kitchen-'.$ticket->id,
            ]);
        }

        if ($event->changeType === 'updated' && $event->status === 'ready') {
            $waiter = $ticket->order->waiter;
            if (! $waiter || ! $waiter->canAuthenticate() || ! $waiter->isWaiter() || ! $waiter->hasPermission('order.view')) return;
            $this->sendToUsers($push, [$waiter->id], 'waiter', [
                'title' => 'Kitchen ticket ready',
                'body' => $ticket->kot_number.' · Table '.($ticket->order->table?->name ?? '—'),
            ], [
                'type' => 'order_ready',
                'ticket_id' => $ticket->id,
                'kitchen_id' => $ticket->kitchen_id,
                'order_id' => $ticket->order_id,
                'order_number' => $ticket->order->order_number,
                'notification_id' => 'order-'.$ticket->order_id,
            ]);
        }
    }

    private function sendToUsers(
        FcmPushService $push,
        array $userIds,
        string $client,
        array $notification,
        array $data,
    ): void {
        if ($userIds === []) return;

        $devices = MobileDeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->where('client', $client)
            ->whereHas('accessToken', fn ($query) => $query->where(fn ($scope) => $scope
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now())))
            ->get();

        foreach ($devices as $device) {
            $push->send($device, $notification, $data);
        }
    }
}
