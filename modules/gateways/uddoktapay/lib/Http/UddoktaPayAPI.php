<?php

namespace WHMCS\Module\Gateway\UddoktaPay\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use WHMCS\Module\Gateway\UddoktaPay\Exception\UddoktaPayException;

/**
 * UddoktaPay Payment Gateway API Client
 */
final class UddoktaPayAPI
{
    const API_HEADER_KEY = 'RT-UDDOKTAPAY-API-KEY';
    const DEFAULT_TIMEOUT = 30;
    const CHECKOUT_V2 = 'checkout-v2';
    const VERIFY_ENDPOINT = 'verify-payment';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiBaseURL;

    /** @var Client */
    private $client;

    /**
     * @param string $apiKey
     * @param string $apiBaseURL
     * @param int $timeout
     * @param bool $verifySsl
     */
    private function __construct($apiKey, $apiBaseURL, $timeout = self::DEFAULT_TIMEOUT, $verifySsl = true)
    {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            throw UddoktaPayException::make('API Key cannot be empty');
        }

        $this->apiKey = $trimmedApiKey;
        $this->apiBaseURL = $this->normalizeBaseURL($apiBaseURL);

        $this->client = new Client([
            'base_uri' => $this->apiBaseURL . '/',
            'timeout' => max(1, $timeout),
            'verify' => $verifySsl,
            'headers' => [
                self::API_HEADER_KEY => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * @param string $apiKey
     * @param string $apiBaseURL
     * @param int $timeout
     * @param bool $verifySsl
     * @return self
     */
    public static function make($apiKey, $apiBaseURL, $timeout = self::DEFAULT_TIMEOUT, $verifySsl = true)
    {
        return new self($apiKey, $apiBaseURL, $timeout, $verifySsl);
    }

    /**
     * @param array $requestData
     * @param string $apiType
     * @return string
     */
    public function initPayment(array $requestData, $apiType = self::CHECKOUT_V2)
    {
        $this->validatePaymentData($requestData);

        $response = $this->sendRequest('POST', $apiType, $requestData);

        if (!isset($response['payment_url'])) {
            $message = isset($response['message']) ? $response['message'] : 'Payment initialization failed';
            throw UddoktaPayException::make($message);
        }

        return $response['payment_url'];
    }

    /**
     * @param string $invoiceId
     * @return array
     */
    public function verifyPayment($invoiceId)
    {
        if (trim($invoiceId) === '') {
            throw UddoktaPayException::make('Invoice ID cannot be empty');
        }

        return $this->sendRequest('POST', self::VERIFY_ENDPOINT, ['invoice_id' => $invoiceId]);
    }

    /**
     * @return array
     */
    public function executePayment()
    {
        $headerKey = 'HTTP_' . str_replace('-', '_', self::API_HEADER_KEY);
        $headerApi = isset($_SERVER[$headerKey]) ? $_SERVER[$headerKey] : null;

        if ($headerApi === null) {
            throw UddoktaPayException::make('Missing API key in request header');
        }

        if ($headerApi !== $this->apiKey) {
            throw UddoktaPayException::make('Invalid API key - Unauthorized');
        }

        $rawInput = trim(file_get_contents('php://input') ?: '');

        if ($rawInput === '') {
            throw UddoktaPayException::make('Empty IPN response body');
        }

        $data = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw UddoktaPayException::make('Invalid JSON in IPN response: ' . json_last_error_msg());
        }

        if (!isset($data['invoice_id'])) {
            throw UddoktaPayException::make('Invoice ID missing in IPN data');
        }

        return $this->verifyPayment($data['invoice_id']);
    }

    /**
     * @param string $apiBaseURL
     * @return string
     */
    private function normalizeBaseURL($apiBaseURL)
    {
        if ($apiBaseURL === '') {
            throw UddoktaPayException::make('API Base URL cannot be empty');
        }

        $baseURL = rtrim($apiBaseURL, '/');
        $apiSegmentPosition = strpos($baseURL, '/api');

        if ($apiSegmentPosition !== false) {
            $baseURL = substr($baseURL, 0, $apiSegmentPosition + 4);
        }

        return $baseURL;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     */
    private function sendRequest($method, $endpoint, array $data)
    {
        try {
            $response = $this->client->request($method, $endpoint, [
                RequestOptions::JSON => $data,
            ]);

            $body = $response->getBody()->getContents();
            $decodedResponse = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw UddoktaPayException::make('Invalid JSON response: ' . json_last_error_msg());
            }

            return $decodedResponse;
        } catch (RequestException $e) {
            throw UddoktaPayException::make($this->extractErrorMessage($e));
        } catch (GuzzleException $e) {
            throw UddoktaPayException::make('Request failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array $data
     * @return void
     */
    private function validatePaymentData(array $data)
    {
        $requiredFields = ['full_name', 'email', 'amount', 'metadata'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw UddoktaPayException::make("Required field missing: {$field}");
            }

            if (is_string($data[$field]) && trim($data[$field]) === '') {
                throw UddoktaPayException::make("Required field cannot be empty: {$field}");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw UddoktaPayException::make('Invalid email address format');
        }

        if (!is_numeric($data['amount'])) {
            throw UddoktaPayException::make('Amount must be a number');
        }

        if ($data['amount'] <= 0) {
            throw UddoktaPayException::make('Amount must be greater than zero');
        }

        if (!is_array($data['metadata'])) {
            throw UddoktaPayException::make('Metadata must be an array');
        }

        $this->validateOptionalUrls($data);
    }

    /**
     * @param array $data
     * @return void
     */
    private function validateOptionalUrls(array $data)
    {
        $urlFields = ['redirect_url', 'cancel_url', 'webhook_url'];

        foreach ($urlFields as $field) {
            if (isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_URL)) {
                $fieldLabel = ucwords(str_replace('_', ' ', $field));
                throw UddoktaPayException::make("Invalid {$fieldLabel} format");
            }
        }
    }

    /**
     * @param RequestException $e
     * @return string
     */
    private function extractErrorMessage(RequestException $e)
    {
        $response = $e->getResponse();

        if ($response === null) {
            return 'Connection failed: ' . $e->getMessage();
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($data['message'])) {
            return $data['message'];
        }

        return 'Request failed with status ' . $response->getStatusCode();
    }
}
