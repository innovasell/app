<?php

class GraphMailer
{
    private $tenantId;
    private $clientId;
    private $clientSecret;
    private $fromEmail;

    public function __construct($config)
    {
        $this->tenantId = $config['tenant_id'];
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->fromEmail = $config['from_email'];
    }

    private function getAccessToken()
    {
        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log("❌ GraphMailer: Erro cURL ao obter token: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        $json = json_decode($response, true);

        if ($httpCode !== 200 || !isset($json['access_token'])) {
            error_log("❌ GraphMailer: Falha ao obter token. HTTP: $httpCode. Resp: " . ($response ?: 'Vazio'));
            return null;
        }

        return $json['access_token'];
    }

    public function sendEmail($toEmail, $subject, $htmlBody, $attachmentPath = null)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Falha na autenticação (Token).'];
        }

        $url = "https://graph.microsoft.com/v1.0/users/{$this->fromEmail}/sendMail";

        $message = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $htmlBody
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $toEmail
                    ]
                ]
            ]
        ];

        // Adicionar anexo se existir
        if ($attachmentPath && file_exists($attachmentPath)) {
            $fileContent = file_get_contents($attachmentPath);
            $base64Content = base64_encode($fileContent);
            $fileName = basename($attachmentPath);

            $message['attachments'] = [
                [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $fileName,
                    'contentType' => 'application/pdf', // Assumindo PDF baseado no uso
                    'contentBytes' => $base64Content
                ]
            ];
        }

        $payload = json_encode(['message' => $message, 'saveToSentItems' => 'true']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'error' => "cURL Erro: $err"];
        }
        curl_close($ch);

        // API retorna 202 Accepted para envio assíncrono bem-sucedido
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => "Graph API Erro ($httpCode): " . $response];
        }
    }
}
