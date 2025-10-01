<?php

namespace Webkul\GraphQLAPI\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\GraphQLAPI\DataGrids\PushNotificationDataGrid;
use Webkul\GraphQLAPI\Http\Requests\NotificationRequest;
use Webkul\GraphQLAPI\Repositories\NotificationRepository;
use Webkul\Product\Repositories\ProductRepository;

class NotificationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CategoryRepository $categoryRepository,
        protected ProductRepository $productRepository,
        protected NotificationRepository $notificationRepository
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PushNotificationDataGrid::class)->process();
        }

        return view('bagisto_graphql::admin.settings.push_notification.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('bagisto_graphql::admin.settings.push_notification.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(NotificationRequest $request)
    {
        Event::dispatch('settings.notification.create.before');

        $notification = $this->notificationRepository->create($request->validated());

        Event::dispatch('settings.notification.create.after', $notification);

        return to_route('admin.settings.push_notification.index')
            ->with('success', trans('bagisto_graphql::app.admin.settings.notification.create-success'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\View\View
     */
    public function edit(int $id)
    {
        $notification = $this->notificationRepository->findOrFail($id);

        return view('bagisto_graphql::admin.settings.push_notification.edit')
            ->with('notification', $notification);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(NotificationRequest $request, int $id)
    {
        Event::dispatch('settings.notification.update.before', $id);

        $notification = $this->notificationRepository->update($request->validated(), $id);

        Event::dispatch('settings.notification.update.after', $notification);

        return to_route('admin.settings.push_notification.index')
            ->with('success', trans('bagisto_graphql::app.admin.settings.notification.update-success'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            Event::dispatch('settings.push-notification.delete.before', $id);

            $this->notificationRepository->delete($id);

            Storage::deleteDirectory("notification/images/$id");

            Event::dispatch('settings.push-notification.delete.after', $id);

            return new JsonResponse([
                'message' => trans('bagisto_graphql::app.admin.settings.notification.delete-success'),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => trans('bagisto_graphql::app.admin.settings.notification.delete-failed'),
            ], 500);
        }
    }

    /**
     * Remove the specified resources from database.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $notificationsIds = $massDestroyRequest->input('indices');

        foreach ($notificationsIds as $notificationsId) {
            Event::dispatch('settings.push-notification.delete.before', $notificationsId);

            $this->notificationRepository->delete($notificationsId);

            Storage::deleteDirectory("notification/images/$notificationsId");

            Event::dispatch('settings.push-notification.delete.after', $notificationsId);
        }

        return new JsonResponse([
            'message' => trans('bagisto_graphql::app.admin.settings.notification.mass-delete-success'),
        ]);
    }

    /**
     * Mass update the notifications.
     */
    public function massUpdate(MassUpdateRequest $request): JsonResponse
    {
        $notificationIds = $request->input('indices');

        foreach ($notificationIds as $notificationId) {
            Event::dispatch('settings.notification.update.before', $notificationId);

            $notification = $this->notificationRepository
                ->where('id', $notificationId)
                ->update(['status' => $request->input('value')]);

            Event::dispatch('settings.notification.update.after', $notification);
        }

        return new JsonResponse([
            'message' => trans('bagisto_graphql::app.admin.settings.notification.mass-update-success'),
        ], 200);
    }

    /**
     * To sent the notification to the device.
     *
     * @return \Illuminate\Http\RedirectResponse
     */

    // النسخة المحسنة من دالة sendNotification

    public function sendNotification($id)
    {
        try {
            $notification = $this->notificationRepository->findOrFail($id);

            $result = $this->notificationRepository->prepareNotification($notification);

            if ($this->isNotificationSent($result)) {
                $this->handleSuccess($result);
            } else {
                $this->handleError($result);
            }

        } catch (\Exception $e) {
            // معالجة الأخطاء العامة
            $this->handleException($e);
        }

        return back();
    }


    private function isNotificationSent($result): bool
    {
        if (is_object($result) && isset($result->message_id)) {
            return true;
        }

        if (is_object($result) && isset($result->name)) {
            return true;
        }

        if (is_array($result) && isset($result['message_id'])) {
            return true;
        }

        if (is_array($result) && isset($result['name'])) {
            return true;
        }

        return false;
    }


    private function handleSuccess($result): void
    {
        $messageId = $this->extractMessageId($result);

        session()->flash('success',
            trans('bagisto_graphql::app.admin.settings.notification.send-success')
        );

        \Log::info('Notification sent successfully', [
            'message_id' => $messageId,
            'result'     => $result,
        ]);
    }


    private function handleError($result): void
    {
        $errorMessage = $this->extractErrorMessage($result);

        session()->flash('error', $errorMessage);

        \Log::error('Notification sending failed', [
            'result' => $result,
            'error'  => $errorMessage,
        ]);
    }


    private function handleException(\Exception $e): void
    {
        $errorMessage = trans('bagisto_graphql::app.admin.settings.notification.exception-error', [
            'message' => $e->getMessage(),
        ]);

        session()->flash('error', $errorMessage);

        \Log::error('Notification sending exception', [
            'exception' => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]);
    }


    private function extractMessageId($result): string
    {
        if (is_object($result)) {
            return $result->message_id ?? $result->name ?? 'unknown';
        }

        if (is_array($result)) {
            return $result['message_id'] ?? $result['name'] ?? 'unknown';
        }

        return 'unknown';
    }


    private function extractErrorMessage($result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if (is_object($result)) {
            if (isset($result->error)) {
                return is_string($result->error) ? $result->error : json_encode($result->error);
            }

            if (isset($result->message)) {
                return $result->message;
            }

            return trans('bagisto_graphql::app.admin.settings.notification.unknown-error');
        }

        if (is_array($result)) {
            if (isset($result['error'])) {
                return is_string($result['error']) ? $result['error'] : json_encode($result['error']);
            }

            if (isset($result['message'])) {
                return $result['message'];
            }

            return trans('bagisto_graphql::app.admin.settings.notification.unknown-error');
        }

        return trans('bagisto_graphql::app.admin.settings.notification.send-error');
    }

    /**
     * To check resource exist in DB.
     *
     * @return JsonResponse
     */
    public function exist()
    {
        $data = request()->only([
            'givenValue',
            'selectedType',
        ]);

        if ($data['selectedType'] == 'product') {
            if ($product = $this->productRepository->find($data['givenValue'])) {
                if (isset($product->url_key)) {
                    return new JsonResponse([
                        'value' => true,
                    ], 200);
                }
            }

            return new JsonResponse([
                'value'   => false,
                'message' => trans('bagisto_graphql::app.admin.settings.notification.product-not-found'),
                'type'    => 'product',
            ], 401);
        }

        if ($data['selectedType'] == 'category') {
            if ($this->categoryRepository->find($data['givenValue'])) {
                return new JsonResponse([
                    'value' => true,
                ], 200);
            }

            return new JsonResponse([
                'value'   => false,
                'message' => trans('bagisto_graphql::app.admin.settings.notification.category-not-found'),
                'type'    => 'category',
            ], 401);
        }
    }
}
