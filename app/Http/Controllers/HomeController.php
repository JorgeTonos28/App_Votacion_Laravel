<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\VotingEvent;
use App\Services\AccessService;
use App\Services\EventSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

class HomeController extends Controller
{
    public function __construct(private readonly AccessService $access, private readonly EventSessionService $sessions) {}

    public function index(?string $code = null)
    {
        $model = ['eventCode' => $code ?? '', 'requiresIdentity' => false, 'requiresAccessCredential' => false, 'accessMode' => null];
        if ($code) {
            try {
                $event = $this->access->findAvailableEvent($code);
                $model = $this->modelForEvent($event);
            } catch (DomainException) {
            }
        }

        return view('home.index', compact('model'));
    }

    public function access(Request $request)
    {
        $data = $request->validate(['event_code' => 'required|string|min:4|max:12', 'display_name' => 'nullable|string|max:180', 'access_credential' => 'nullable|string|max:180'], ['event_code.required' => 'Ingresa el código del evento.']);
        $model = ['eventCode' => $data['event_code'], 'requiresIdentity' => false, 'requiresAccessCredential' => false, 'accessMode' => null];
        try {
            $event = $this->access->findAvailableEvent($data['event_code']);
            $model = $this->modelForEvent($event);
            if (trim($data['display_name'] ?? '') === '') {
                return $this->accessError($request, $model, 'display_name', 'Indica tu nombre para continuar.');
            }
            $deviceId = $request->cookie('innovamente_device') ?: Str::uuid()->toString();
            [$token] = $this->sessions->createPublic($event, $deviceId, $data['access_credential'] ?? null, $data['display_name'], $request->userAgent());
        } catch (DomainException $exception) {
            return $this->accessError($request, $model, 'event_code', $exception->getMessage());
        }

        return redirect()->route('public.lobby')
            ->cookie('innovamente_device', $deviceId, 525600, null, null, $request->isSecure(), true, false, 'Lax')
            ->cookie('innovamente_public', $token, 720, null, null, $request->isSecure(), true, false, 'Lax');
    }

    public function error(?string $code = null)
    {
        $message = match ($code) {
            'SESSION_EXPIRED' => 'Tu sesión expiró. Vuelve a ingresar al evento.','EVENT_FINISHED' => 'El evento ya finalizó.','RATE_LIMITED' => 'Se realizaron demasiados intentos. Espera unos minutos.',default => 'No pudimos completar la acción. Intenta nuevamente.'
        };

        return view('home.error', compact('message'));
    }

    private function modelForEvent(VotingEvent $event): array
    {
        return [
            'eventCode' => $event->code,
            'requiresIdentity' => true,
            'requiresAccessCredential' => in_array($event->public_access_mode, ['IndividualCode', 'AttendeeList'], true),
            'accessMode' => $event->public_access_mode,
        ];
    }

    private function accessError(Request $request, array $model, string $field, string $message)
    {
        $request->flash();
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag([$field => [$message]]));

        return view('home.index', compact('model', 'errors'));
    }
}
