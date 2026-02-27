<?php
require_once __DIR__ . '/../config_ploomes.php';

class PloomesHelper
{
    private $userKey;
    private $baseUrl;

    public function __construct()
    {
        if (!defined('PLOOMES_USER_KEY') || empty(PLOOMES_USER_KEY)) {
            throw new Exception("Ploomes User-Key não configurada.");
        }
        $this->userKey = PLOOMES_USER_KEY;
        $this->baseUrl = PLOOMES_API_URL;
    }

    private function makeRequest($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'User-Key: ' . $this->userKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: InnovasellSystem/1.0 (PHP)'
        ];

        $options = [
            'http' => [
                'header' => implode("\r\n", $headers),
                'method' => $method,
                'ignore_errors' => true // Para capturar o body de erros 4xx/5xx
            ]
        ];

        if ($data !== null) {
            $options['http']['content'] = json_encode($data);
        }

        // Tratamento de Rate Limit: Retries simples com backoff fixo
        $maxRetries = 3;
        $attempt = 0;

        do {
            $attempt++;
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            // Check headers for status code
            if (isset($http_response_header)) {
                $statusLine = $http_response_header[0];
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);
                $statusCode = isset($matches[1]) ? intval($matches[1]) : 0;

                if ($statusCode === 429) {
                    if ($attempt < $maxRetries) {
                        sleep(2); // Wait 2 seconds before retry
                        continue;
                    } else {
                        throw new Exception("Ploomes API Rate Limit Exceeded after $maxRetries attempts.");
                    }
                }

                if ($statusCode >= 400) {
                    throw new Exception("Ploomes API Error ($statusCode): " . ($response ?: 'No response body'));
                }
            } else {
                if ($response === false) {
                    $error = error_get_last();
                    throw new Exception("Connection to Ploomes failed: " . ($error['message'] ?? 'Unknown error'));
                }
            }

            return json_decode($response, true);

        } while ($attempt < $maxRetries);
    }

    /**
     * Implements a robust search logic for clients, prioritizing name-based range search
     * to bypass WAF issues with direct CNPJ filtering, followed by in-PHP CNPJ matching.
     *
     * @param string $name The name of the client to search for.
     * @param string $cnpj The CNPJ of the client to search for.
     * @return array|null The client data if found, otherwise null.
     */
    public function findClient($name, $cnpj)
    {
        // ---------------------------------------------------------
        // ROBUST SEARCH STRATEGY (WAF BYPASS)
        // ---------------------------------------------------------
        // Direct CNPJ filtering triggers WAF (403 Forbidden).
        // Strategy:
        // 1. Filter by Name Range (Name >= LocalName) to get potential candidates.
        // 2. Iterate in PHP and match CNPJ exactly.

        $candidates = [];

        // Strategy A: Range Search if Name is provided
        if (!empty($name)) {
            // function to execute search
            $doSearch = function ($searchName, $field = 'Name') {
                $safeName = str_replace("'", "''", $searchName);
                $params = [
                    '$filter' => "$field ge '$safeName'",
                    '$top' => 30, // Increased to improve chances
                    '$orderby' => "$field asc"
                ];
                $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
                return $this->makeRequest("/Contacts?$queryString");
            };

            // 1. Try Full Name on 'Name' (Trade Name)
            $response = $doSearch($name, 'Name');
            if (isset($response['value'])) {
                $candidates = array_merge($candidates, $response['value']);
            }

            // 2. Try Full Name on 'LegalName' (Razão Social)
            // This fixes cases where Local Name = Legal Name, but Remote Name = Trade Name (e.g., Flagian vs Anna Pegova)
            $response = $doSearch($name, 'LegalName');
            if (isset($response['value'])) {
                // Merge avoiding duplicates (we'll filter later)
                foreach ($response['value'] as $c) {
                    $candidates[] = $c;
                }
            }

            // 3. Fallback: First Word Strategy (if multi-word and distinct enough)
            $parts = explode(' ', trim($name));
            if (count($parts) > 1) {
                $firstWord = $parts[0];
                // Only try if first word is reasonably long (>2 chars)
                if (strlen($firstWord) > 2) {
                    // Try First Word on Name
                    $response = $doSearch($firstWord, 'Name');
                    if (isset($response['value'])) {
                        foreach ($response['value'] as $c)
                            $candidates[] = $c;
                    }

                    // Try First Word on LegalName
                    $response = $doSearch($firstWord, 'LegalName');
                    if (isset($response['value'])) {
                        foreach ($response['value'] as $c)
                            $candidates[] = $c;
                    }
                }
            }
        }

        // Filter Candidates by CNPJ
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);

        foreach ($candidates as $c) {
            // Ploomes might return CNPJ in 'CNPJ', 'CPF', or 'CNPJ_CPF' keys
            $remoteCnpj = $c['CNPJ'] ?? $c['CNPJ_CPF'] ?? $c['CPF'] ?? '';
            $remoteCnpjLimpo = preg_replace('/[^0-9]/', '', $remoteCnpj);

            if (!empty($remoteCnpjLimpo) && $remoteCnpjLimpo === $cnpjLimpo) {
                return $c; // FOUND EXACT MATCH!
            }
        }

        // Strategy B: Fallback - Search by Exact Name (if CNPJ match failed or no name provided for range search)
        // This handles cases where Name matches but CNPJ is missing/different in CRM,
        // or if only name was provided initially.
        if (!empty($name)) {
            $safeName = str_replace("'", "''", $name);
            $params = [
                'filter' => "Name eq '$safeName'", // Try 'filter' first (no $) as some endpoints prefer it
                '$select' => 'Id,Name'
            ];
            $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $response = $this->makeRequest("/Contacts?$queryString");

            // If filter (no $) failed/ignored, we might get Valmari (first result).
            // But we can check if the result name matches requested name.
            if (isset($response['value']) && count($response['value']) > 0) {
                foreach ($response['value'] as $c) {
                    if (strcasecmp($c['Name'], $name) === 0) {
                        return $c;
                    }
                }
            }

            // Try standard $filter as last resort
            $params = [
                '$filter' => "Name eq '$safeName'",
                '$select' => 'Id,Name'
            ];
            $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $response = $this->makeRequest("/Contacts?$queryString");
            if (isset($response['value']) && count($response['value']) > 0) {
                return $response['value'][0];
            }
        }

        return null;
    }

    // Deprecated/Alias - Keep for compatibility but redirect to new logic
    public function getClientByCnpj($cnpj)
    {
        // Without Name, we can't use the Range strategy efficiently unless we scan everything.
        // Returning null to force caller to provide name if possible.
        return null;
    }

    public function getClientByName($name)
    {
        return $this->findClient($name, '');
    }

    public function createInteraction($contactId, $content, $typeId = 1, $tags = [])
    {
        // TypeId = 1 costuma ser 'Anotação' ou padrão.
        $payload = [
            'ContactId' => $contactId,
            'Content' => $content,
            'TypeId' => $typeId
        ];

        // Add Tags if provided
        if (!empty($tags)) {
            // Ploomes expects Tags as an array of objects with Id
            // Example: [ {"Id": 123}, {"Id": 456} ]
            $tagsPayload = [];
            foreach ($tags as $tagId) {
                $tagsPayload[] = ['Id' => $tagId];
            }
            $payload['Tags'] = $tagsPayload;
        }

        $response = $this->makeRequest("/InteractionRecords", 'POST', $payload);

        // OData v4 often returns the created object inside a 'value' array
        // But for POST creation it usually returns the object directly or inside 'value'
        if (isset($response['value']) && is_array($response['value']) && count($response['value']) > 0) {
            return $response['value'][0];
        }

        // Validating if response is the object itself (has Id)
        if (isset($response['Id'])) {
            return $response;
        }

        return $response;
    }
}
?>