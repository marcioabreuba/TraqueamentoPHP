<?php

namespace App\Http\Controllers;

use App\Events\Purchase;
use App\Models\User;
use Esign\ConversionsApi\Facades\ConversionsApi;
use FacebookAds\Object\ServerSide\CustomData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class YampiController extends Controller
{
    public function isValidSignature($payload, $signature, $secret)
    {
        // Gera a assinatura HMAC
        $calculatedSignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        // Compare a assinatura recebida com a calculada
        return hash_equals($calculatedSignature, $signature);
    }

    public function Yampi(Request $request)
    {
        try {
            $payload = $request->getContent();
            $headers = $request->headers;
            
            $webhookSecret = env('YAMPI_WEBHOOK_SECRET');
            $signature = $headers->get('X-Yampi-Hmac-SHA256');

            if (!$this->isValidSignature($payload, $signature, $webhookSecret)) {
                Log::error('[WEBHOOK ERROR] Assinatura inválida.');
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            $data = json_decode($payload, true);
            if (!$data || !isset($data['event'])) {
                Log::error('[WEBHOOK ERROR] Payload inválido: ' . $payload);
                return response()->json(['error' => 'Invalid payload'], 400);
            }
            Log::info('[WEBHOOK SUCCESS] Payload validado com sucesso: ' . json_encode($data));

            $fn = $data['resource']['customer']['data']['first_name'] ?? '';
            $ln = $data['resource']['customer']['data']['last_name'] ?? '';
            $em = $data['resource']['customer']['data']['email'] ?? '';
            $ph = $data['resource']['customer']['data']['phone']['full_number'] ?? '';
            $origin = $data['resource']['data']['utm_source'] ?? '';
            $currency = 'BRL';
            $price = $data['resource']['data']['value_total'] ?? 0;

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
