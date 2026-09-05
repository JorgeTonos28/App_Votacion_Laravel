<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\Voter;
use App\Models\VotingEvent;
use App\Services\AccessService;
use App\Services\EventSessionService;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

class HomeController extends Controller
{
    public function __construct(private readonly AccessService $access, private readonly EventSessionService $sessions) {}

    public function index(Request $request, ?string $code = null)
    {
        // 1. Si el usuario ya cuenta con una sesión pública activa y válida, redirigir directo al lobby
        $sessionToken = $request->cookie('innovamente_public');
        if ($sessionToken) {
            try {
                $session = $this->sessions->validate($sessionToken, 'Public');
                if ($session && isset($session['event'])) {
                    if (!$code || strtoupper($session['event']->code) === strtoupper($code)) {
                        return redirect()->route('public.lobby');
                    }
                }
            } catch (\Throwable) {
            }
        }

        $deviceId = $request->input('device_id')
            ?: $request->cookie('innovamente_client_device')
            ?: $request->cookie('innovamente_device')
            ?: Str::uuid()->toString();

        $model = ['eventCode' => $code ?? '', 'requiresIdentity' => false, 'requiresAccessCredential' => false, 'accessMode' => null];
        if ($code) {
            try {
                $event = $this->access->findAvailableEvent($code);
                $model = $this->modelForEvent($event);

                // 2. Si el evento es por Dispositivo (o híbrido) y este dispositivo ya se había registrado previamente
                if (!in_array($event->public_access_mode, ['IndividualCode', 'AttendeeList'], true)) {
                    $deviceHash = Domain::technicalHash($deviceId);
                    $existingVoter = Voter::query()
                        ->where('event_id', $event->id)
                        ->where('device_hash', $deviceHash)
                        ->where('status', 'Active')
                        ->first();

                    if ($existingVoter && !empty($existingVoter->display_name)) {
                        [$token] = $this->sessions->createPublic($event, $deviceId, null, $existingVoter->display_name, $request->userAgent());
                        return redirect()->route('public.lobby')
                            ->cookie('innovamente_device', $deviceId, 525600, '/', null, $request->isSecure(), false, false, 'Lax')
                            ->cookie('innovamente_client_device', $deviceId, 525600, '/', null, $request->isSecure(), false, false, 'Lax')
                            ->cookie('innovamente_public', $token, 720, '/', null, $request->isSecure(), true, false, 'Lax');
                    }
                }
            } catch (DomainException) {
            }
        }

        $response = response()->view('home.index', compact('model'));
        $response->cookie('innovamente_device', $deviceId, 525600, '/', null, $request->isSecure(), false, false, 'Lax');
        $response->cookie('innovamente_client_device', $deviceId, 525600, '/', null, $request->isSecure(), false, false, 'Lax');

        return $response;
    }

    public function access(Request $request)
    {
        $data = $request->validate([
            'event_code' => 'required|string|min:4|max:12',
            'device_id' => 'nullable|string|max:120',
            'display_name' => 'nullable|string|max:180',
            'access_credential' => 'nullable|string|max:180'
        ], [
            'event_code.required' => 'Ingresa el código del evento.'
        ]);

        $model = ['eventCode' => $data['event_code'], 'requiresIdentity' => false, 'requiresAccessCredential' => false, 'accessMode' => null];
        try {
            $event = $this->access->findAvailableEvent($data['event_code']);
            $model = $this->modelForEvent($event);

            $deviceId = ($data['device_id'] ?? null)
                ?: $request->cookie('innovamente_client_device')
                ?: $request->cookie('innovamente_device')
                ?: Str::uuid()->toString();

            $deviceHash = Domain::technicalHash($deviceId);
            $existingVoter = Voter::query()
                ->where('event_id', $event->id)
                ->where('device_hash', $deviceHash)
                ->where('status', 'Active')
                ->first();

            $displayName = trim($data['display_name'] ?? '');
            if ($displayName === '' && $existingVoter && !empty($existingVoter->display_name)) {
                $displayName = $existingVoter->display_name;
            }

            if ($displayName === '') {
                return $this->accessError($request, $model, 'display_name', 'Indica tu nombre para continuar.');
            }

            [$token] = $this->sessions->createPublic($event, $deviceId, $data['access_credential'] ?? null, $displayName, $request->userAgent());
        } catch (DomainException $exception) {
            return $this->accessError($request, $model, 'event_code', $exception->getMessage());
        }

        return redirect()->route('public.lobby')
            ->cookie('innovamente_device', $deviceId, 525600, '/', null, $request->isSecure(), false, false, 'Lax')
            ->cookie('innovamente_client_device', $deviceId, 525600, '/', null, $request->isSecure(), false, false, 'Lax')
            ->cookie('innovamente_public', $token, 720, '/', null, $request->isSecure(), true, false, 'Lax');
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
