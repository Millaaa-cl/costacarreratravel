<?php
declare(strict_types=1);

/**
 * Cliente mínimo de la API REST de Flow.cl (https://www.flow.cl/docs/api.html).
 * Firma: HMAC-SHA256 de "clave1valor1clave2valor2..." con las claves en orden alfabético.
 */
final class Flow
{
    private string $apiKey;
    private string $secret;
    private string $base;

    public function __construct(array $cfg)
    {
        $this->apiKey = $cfg['api_key'];
        $this->secret = $cfg['secret_key'];
        $this->base = !empty($cfg['sandbox']) ? 'https://sandbox.flow.cl/api' : 'https://www.flow.cl/api';
    }

    public function sign(array $params): string
    {
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) $str .= $k . $v;
        return hash_hmac('sha256', $str, $this->secret);
    }

    /** Crea una orden de pago. Devuelve ['url' => ..., 'token' => ..., 'flowOrder' => ...]. */
    public function createPayment(array $p): array
    {
        return $this->request('POST', '/payment/create', $p);
    }

    /** Estado: 1 pendiente, 2 pagada, 3 rechazada, 4 anulada. */
    public function getStatus(string $token): array
    {
        return $this->request('GET', '/payment/getStatus', ['token' => $token]);
    }

    private function request(string $method, string $service, array $params): array
    {
        $params['apiKey'] = $this->apiKey;
        $params = array_map('strval', $params);
        $params['s'] = $this->sign($params);
        $url = $this->base . $service;

        $ch = curl_init();
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) throw new RuntimeException("Flow: error de conexión ($err)");
        $data = json_decode((string)$body, true);
        if ($code !== 200 || !is_array($data)) {
            $msg = is_array($data) ? ($data['message'] ?? 'desconocido') : substr((string)$body, 0, 200);
            throw new RuntimeException("Flow $service HTTP $code: $msg");
        }
        return $data;
    }
}
