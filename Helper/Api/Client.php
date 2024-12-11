<?php

namespace Pledg\PledgPaymentGateway\Helper\Api;

class Client
{
    public function get(string $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * @throws \Exception
     */
    public function post(string $url, array $options): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $decodedResponse = json_decode($response, true);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
        } elseif (!$decodedResponse) {
            $error = $response;
        } elseif (array_key_exists('error', $decodedResponse)) {
            $error = $decodedResponse['error']['debug'];
        }

        curl_close($ch);

        if (isset($error)) {
            throw new \Exception($error);
        }

        return $decodedResponse;
    }
}
