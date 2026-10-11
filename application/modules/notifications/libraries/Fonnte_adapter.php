<?php

defined('BASEPATH') || exit('No direct script access allowed');

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class Fonnte_adapter
{
    private ClientInterface $client;

    public function __construct(private string $token, ?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function send(string $recipient, string $message): array
    {
        if ($this->token === '') {
            return $this->result('rejected', 'token_missing');
        }
        try {
            $response = $this->client->request('POST', 'https://api.fonnte.com/send', [
                'headers'         => ['Authorization' => $this->token],
                'form_params'     => ['target' => $recipient, 'message' => $message, 'countryCode' => '0', 'preview' => 'false', 'connectOnly' => 'true'],
                'connect_timeout' => 3,
                'timeout'         => 8,
                'verify'          => true,
                'allow_redirects' => false,
                'http_errors'     => false,
            ]);
        } catch (GuzzleException $e) {
            // Exception text can contain both Authorization and the public invoice link.
            return $this->result('uncertain', 'transport_uncertain');
        }
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && is_array($data)) {
            if (($data['status'] ?? $data['Status'] ?? null) === false) {
                return $this->result('rejected', 'provider_rejected');
            }
            $id = is_array($data['id'] ?? null) && count($data['id']) === 1 ? ($data['id'][0] ?? null) : null;
            if (($data['status'] ?? null) === true && is_scalar($id) && preg_match('/^[A-Za-z0-9_-]{1,191}$/D', (string) $id)) {
                return ['status' => 'accepted', 'provider_id' => (string) $id, 'reason' => ''];
            }
        }

        return $this->result('uncertain', 'response_uncertain');
    }

    private function result(string $status, string $reason): array
    {
        return ['status' => $status, 'provider_id' => null, 'reason' => $reason];
    }
}
