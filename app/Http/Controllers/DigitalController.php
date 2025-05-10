<?php

namespace App\Http\Controllers;

use App\Events\Purchase;
use App\Models\User;
use Esign\ConversionsApi\Facades\ConversionsApi;
use FacebookAds\Object\ServerSide\CustomData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class DigitalController extends Controller
{
    public function Digital(Request $request)
    {
        try {
            // Log::info('Recebendo Payload:', $request->all());
            $json = $request->all();

            $fullName = strtolower(trim($json['contact']['name'] ?? ''));
            $names = explode(' ', $fullName);
            $fn = $names[0] ?? '';
            $ln = count($names) > 1 ? $names[count($names) - 1] : '';
            $em = strtolower($json['contact']['email'] ?? '');
            $ph = preg_replace('/\D/', '', ($json['contact']['phone_local_code'] ?? '') . ($json['contact']['phone_number'] ?? ''));
            $origin = $json['source']['utm_source'] ?? '';
            $currency = $json['payment']['currency'] ?? 'BRL';
            $price = $json['payment']['total'] ?? 0;

            // Log::info('Dados do comprador:', [
            //     'Primeiro Nome' => $fn,
            //     'Último Nome' => $ln,
            //     'Email' => $em,
            //     'Telefone' => $ph,
            //     'Origem' => $origin,
            //     'Moeda' => $currency,
            //     'Preço' => $price
            // ]);

            $user = User::where('external_id', $origin)->first();
            if ($user) {
                $user->update([
                    'fn' => $fn,
                    'ln' => $ln,
                    'em' => $em,
                    'ph' => $ph,
                ]);
            } else {
                $user = User::create([
                    'fn' => $fn,
                    'ln' => $ln,
                    'em' => $em,
                    'ph' => $ph,
                ]);
            }

            $contentId = $user->content_id ?? '';
            $external_id = $user->external_id ?? '';
            $client_ip_address = $user->client_ip_address ?? '';
            $client_user_agent = $user->client_user_agent ?? '';
            $fbp = $user->fbp ?? '';
            $fbc = $user->fbc ?? '';
            $country = $user->country ?? '';
            $st = $user->st ?? '';
            $ct = $user->ct ?? '';
            $zp = $user->zp ?? '';
            $fn = $user->fn ?? '';
            $ln = $user->ln ?? '';
            $em = $user->em ?? '';
            $ph = $user->ph ?? '';

            // Apenas para os usuários da minha Api
            $domains = config('conversions.domains');
            if (isset($domains[$contentId])) {
                $config = $domains[$contentId];
                Config::set('conversions-api.pixel_id', $config['pixel_id']);
                Config::set('conversions-api.access_token', $config['access_token']);
                Config::set('conversions-api.test_code', $config['test_code']);
            } else {
                Log::info('[ERROR][WEBHOOKS] Não achou o produto no banco de dados: ' . $contentId);
            }

            $event = Purchase::create();
            $advancedMatching = $event->getUserData()
                ->setExternalId($external_id)
                ->setClientIpAddress($client_ip_address)
                ->setClientUserAgent($client_user_agent)
                ->setFbp($fbp)
                ->setFbc($fbc)
                ->setCountryCode($country)
                ->setState($st)
                ->setCity($ct)
                ->setZipCode($zp)
                ->setFirstName($fn)
                ->setLastName($ln)
                ->setEmail($em)
                ->setPhone($ph);
            $event->setUserData($advancedMatching);
            $event->setCustomData((new CustomData())->setContentIds([$contentId])->setCurrency($currency)->setValue($price));

            ConversionsApi::addEvent($event)->sendEvents();

            $log = [
                'event_id' => $event->getEventId(),
                'event_name' => $event->getEventName(),
                'event_time' => $event->getEventTime(),
                'event_source_url' => $event->getEventSourceUrl(),
                'user_data' => [
                    'client_user_agent' => $client_user_agent,
                    'client_ip_address' => $client_ip_address,
                    'fbc' => $fbc,
                    'fbp' => $fbp,
                    'external_id' => $external_id,
                    'country' => $country,
                    'state' => $st,
                    'city' => $ct,
                    'postal_code' => $zp,
                    'fn' => $fn,
                    'ln' => $ln,
                    'em' => $em,
                    'ph' => $ph,
                ],
            ];
            Log::channel('Events')->info(json_encode($log, JSON_PRETTY_PRINT));

            return response()->json(['message' => 'Webhook processado com sucesso']);
        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro interno no servidor'], 500);
        }
    }
}
