<?php

declare(strict_types=1);

/**
 * Pure source contract for one Store Lite subscription intent.
 *
 * This source has no package registration, secret, database, transport,
 * provider request, browser navigation, or persistence path. It prepares the
 * exact hosted Stripe form and accepts only a synthetic bounded response into
 * a transient, still-unauthorized redirect handoff.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract
{
    private const PACKAGE_ID = 'redcms.store-lite-stripe-checkout';
    private const CONTRACT_VERSION = 'subscription-checkout-v1';
    private const OPERATION = 'subscription.checkout.prepare-sandbox';
    private const API_VERSION = '2024-09-30.acacia';
    private const MAX_BODY_BYTES = 65536;

    public static function prepare(
        array $intent,
        array $offer,
        array $policy
    ): array {
        if (!self::intent($intent)
            || !self::offer($offer)
            || !hash_equals(
                $intent['offerStateSha256'],
                self::hash($offer)
            )
        ) {
            return self::prepareInvalid('subscription_intent_refused');
        }
        $normalizedPolicy = self::policy($policy);
        if ($normalizedPolicy === null) {
            return self::prepareInvalid('subscription_policy_refused');
        }

        $interval = $offer['billingPeriod'] === 'monthly'
            ? 'month'
            : 'year';
        $form = [
            'mode' => 'subscription',
            'ui_mode' => 'hosted',
            'submit_type' => 'subscribe',
            'success_url' => $normalizedPolicy['successUrl'],
            'cancel_url' => $normalizedPolicy['cancelUrl'],
            'client_reference_id' => $intent['intentReference'],
            'metadata[redcms_intent_state_sha256]' =>
                $intent['intentStateSha256'],
            'metadata[redcms_offer_state_sha256]' =>
                $intent['offerStateSha256'],
            'subscription_data[metadata][redcms_intent_state_sha256]' =>
                $intent['intentStateSha256'],
            'subscription_data[metadata][redcms_offer_state_sha256]' =>
                $intent['offerStateSha256'],
            'line_items[0][price_data][currency]' =>
                strtolower($offer['currency']),
            'line_items[0][price_data][unit_amount]' =>
                (string) $offer['priceMinor'],
            'line_items[0][price_data][recurring][interval]' => $interval,
            'line_items[0][price_data][recurring][interval_count]' => '1',
            'line_items[0][price_data][product_data][name]' => $offer['title'],
        ];
        if ($offer['summary'] !== null) {
            $form['line_items[0][price_data][product_data][description]'] =
                $offer['summary'];
        }
        $form['line_items[0][quantity]'] = '1';
        $form['expires_at'] = (string) $normalizedPolicy['expiresAtEpoch'];

        $pairs = [];
        foreach ($form as $key => $value) {
            $pairs[] = urlencode($key) . '=' . urlencode($value);
        }
        $body = implode('&', $pairs);
        $bodyBytes = strlen($body);
        if ($bodyBytes < 1 || $bodyBytes > self::MAX_BODY_BYTES) {
            return self::prepareInvalid('subscription_request_refused');
        }

        $contract = [
            'schema' => 1,
            'packageId' => self::PACKAGE_ID,
            'contractVersion' => self::CONTRACT_VERSION,
            'operation' => self::OPERATION,
            'sourceStoreLiteVersion' => '0.1.48',
            'contactTarget' => 'stripe-sandbox',
            'intent' => [
                'intentReference' => $intent['intentReference'],
                'intentStateSha256' => $intent['intentStateSha256'],
                'offerStateSha256' => $intent['offerStateSha256'],
            ],
            'offer' => [
                'offerId' => $offer['id'],
                'productId' => $offer['productId'],
                'variantId' => $offer['variantId'],
                'currency' => $offer['currency'],
                'priceMinor' => $offer['priceMinor'],
                'billingPeriod' => $offer['billingPeriod'],
            ],
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.stripe.com/v1/checkout/sessions',
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Stripe-Version' => self::API_VERSION,
                ],
                'authorization' => [
                    'mode' => 'restricted_test_write',
                    'secretSettingKey' => 'stripe.secret-key',
                    'valueIncluded' => false,
                ],
                'body' => $body,
                'bodyBytes' => $bodyBytes,
                'bodySha256' => hash('sha256', $body),
                'transport' => [
                    'scheme' => 'https',
                    'minimumTls' => 'TLSv1.2',
                    'verifyPeer' => true,
                    'verifyHost' => true,
                    'followRedirects' => false,
                    'proxyAllowed' => false,
                    'connectTimeoutSeconds' => 5,
                    'totalTimeoutSeconds' => 15,
                    'maximumResponseBytes' => 262144,
                    'automaticRetry' => false,
                ],
            ],
            'expiry' => [
                'createdAtEpoch' => $normalizedPolicy['createdAtEpoch'],
                'expiresAtEpoch' => $normalizedPolicy['expiresAtEpoch'],
                'durationSeconds' => 1800,
                'recoveryEnabled' => false,
            ],
            'redirectPolicy' => [
                'providerOrigin' => 'https://checkout.stripe.com',
                'navigationMode' => 'location.assign',
                'transientOnly' => true,
                'persistCheckoutUrl' => false,
                'cacheControl' => 'no-store',
                'authorizationRequired' => true,
                'browserNavigationAuthorized' => false,
            ],
            'currentExecution' => [
                'network' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'checkoutCreation' => false,
                'customerCreation' => false,
                'subscriptionCreation' => false,
                'payment' => false,
                'webhook' => false,
                'browserNavigation' => false,
                'storeLiteMutation' => false,
                'clientDeployment' => false,
            ],
        ];
        $contractSha256 = self::hash($contract);
        if (!self::sha256($contractSha256)) {
            return self::prepareInvalid('subscription_contract_encoding_failed');
        }
        return [
            'valid' => true,
            'contract' => $contract,
            'contractSha256' => $contractSha256,
            'errors' => [],
        ];
    }

    public static function accept(
        array $intent,
        array $offer,
        array $policy,
        array $envelope,
        array $projection
    ): array {
        $prepared = self::prepare($intent, $offer, $policy);
        if (($prepared['valid'] ?? null) !== true
            || !self::envelope($envelope)
            || !self::projection(
                $projection,
                $intent,
                $offer,
                $policy['expiresAtEpoch'] ?? null
            )
        ) {
            return self::acceptInvalid('subscription_response_refused');
        }
        $handoff = [
            'intentReference' => $intent['intentReference'],
            'checkoutSessionRef' => $projection['id'],
            'checkoutUrl' => $projection['url'],
            'expiresAtEpoch' => $projection['expires_at'],
            'navigationMode' => 'location.assign',
            'transientOnly' => true,
            'persistCheckoutUrl' => false,
            'cacheControl' => 'no-store',
            'authorizationRequired' => true,
            'browserNavigationAuthorized' => false,
        ];
        $responseEvidenceSha256 = self::hash([
            'requestId' => $envelope['requestId'],
            'bodySha256' => $envelope['bodySha256'],
            'bodyBytes' => $envelope['bodyBytes'],
            'tlsVersion' => $envelope['tlsVersion'],
            'checkoutSessionRef' => $projection['id'],
            'intentReference' => $intent['intentReference'],
        ]);
        $resultSha256 = self::hash([
            'contractSha256' => $prepared['contractSha256'],
            'responseEvidenceSha256' => $responseEvidenceSha256,
            'handoffSha256' => hash('sha256', $projection['url']),
        ]);
        return [
            'valid' => true,
            'handoff' => $handoff,
            'contractSha256' => $prepared['contractSha256'],
            'responseEvidenceSha256' => $responseEvidenceSha256,
            'resultSha256' => $resultSha256,
            'errors' => [],
        ];
    }

    private static function intent(array $intent): bool
    {
        return self::exactKeys($intent, [
            'intentReference', 'intentStateSha256', 'offerStateSha256',
            'status',
        ])
            && is_string($intent['intentReference'] ?? null)
            && preg_match(
                '/\Asint_[a-f0-9]{32}\z/D',
                $intent['intentReference']
            ) === 1
            && self::sha256($intent['intentStateSha256'] ?? null)
            && self::sha256($intent['offerStateSha256'] ?? null)
            && ($intent['status'] ?? null) === 'requested';
    }

    private static function offer(array $offer): bool
    {
        if (!self::exactKeys($offer, [
            'id', 'productId', 'variantId', 'title', 'summary', 'currency',
            'priceMinor', 'billingPeriod', 'state', 'availability',
            'buttonLabel',
        ])
            || !self::identifier($offer['id'] ?? null)
            || !self::identifier($offer['productId'] ?? null)
            || ($offer['variantId'] !== null
                && !self::identifier($offer['variantId']))
            || !self::text($offer['title'] ?? null, 160, false)
            || ($offer['summary'] !== null
                && !self::text($offer['summary'], 1000, false))
            || !is_string($offer['currency'] ?? null)
            || preg_match('/\A[A-Z]{3}\z/D', $offer['currency']) !== 1
            || !is_int($offer['priceMinor'] ?? null)
            || $offer['priceMinor'] < 0
            || $offer['priceMinor'] > 999999999
            || !in_array(
                $offer['billingPeriod'] ?? null,
                ['monthly', 'yearly'],
                true
            )
            || ($offer['state'] ?? null) !== 'published'
            || ($offer['availability'] ?? null) !== 'available'
            || !self::text($offer['buttonLabel'] ?? null, 80, false)
        ) {
            return false;
        }
        return true;
    }

    private static function policy(array $policy): ?array
    {
        if (!self::exactKeys($policy, [
            'apiVersion', 'successUrl', 'cancelUrl', 'createdAtEpoch',
            'expiresAtEpoch',
        ])
            || ($policy['apiVersion'] ?? null) !== self::API_VERSION
            || !self::httpsUrl($policy['successUrl'] ?? null)
            || !self::httpsUrl($policy['cancelUrl'] ?? null)
            || !self::sameOrigin($policy['successUrl'], $policy['cancelUrl'])
            || !is_int($policy['createdAtEpoch'] ?? null)
            || $policy['createdAtEpoch'] < 1
            || !is_int($policy['expiresAtEpoch'] ?? null)
            || $policy['expiresAtEpoch'] - $policy['createdAtEpoch'] !== 1800
        ) {
            return null;
        }
        return $policy;
    }

    private static function envelope(array $envelope): bool
    {
        return self::exactKeys($envelope, [
            'statusCode', 'contentType', 'bodyBytes', 'bodySha256',
            'requestId', 'tlsVersion', 'redirectCount',
        ])
            && ($envelope['statusCode'] ?? null) === 200
            && is_string($envelope['contentType'] ?? null)
            && preg_match(
                '/\Aapplication\/json(?:;\s*charset=utf-8)?\z/Di',
                $envelope['contentType']
            ) === 1
            && is_int($envelope['bodyBytes'] ?? null)
            && $envelope['bodyBytes'] >= 2
            && $envelope['bodyBytes'] <= 262144
            && self::sha256($envelope['bodySha256'] ?? null)
            && is_string($envelope['requestId'] ?? null)
            && preg_match(
                '/\Areq_[A-Za-z0-9]{8,128}\z/D',
                $envelope['requestId']
            ) === 1
            && in_array(
                $envelope['tlsVersion'] ?? null,
                ['TLSv1.2', 'TLSv1.3'],
                true
            )
            && ($envelope['redirectCount'] ?? null) === 0;
    }

    private static function projection(
        array $projection,
        array $intent,
        array $offer,
        mixed $expiresAtEpoch
    ): bool {
        if (!self::exactKeys($projection, [
            'id', 'object', 'url', 'mode', 'status', 'payment_status',
            'amount_total', 'currency', 'client_reference_id', 'metadata',
            'livemode', 'expires_at', 'after_expiration',
        ])
            || !self::sessionId($projection['id'] ?? null)
            || ($projection['object'] ?? null) !== 'checkout.session'
            || !self::checkoutUrl(
                $projection['url'] ?? null,
                $projection['id']
            )
            || ($projection['mode'] ?? null) !== 'subscription'
            || ($projection['status'] ?? null) !== 'open'
            || ($projection['payment_status'] ?? null) !== 'unpaid'
            || ($projection['amount_total'] ?? null) !== $offer['priceMinor']
            || ($projection['currency'] ?? null)
                !== strtolower($offer['currency'])
            || ($projection['client_reference_id'] ?? null)
                !== $intent['intentReference']
            || !is_array($projection['metadata'] ?? null)
            || $projection['livemode'] !== false
            || $projection['expires_at'] !== $expiresAtEpoch
            || $projection['after_expiration'] !== null
        ) {
            return false;
        }
        return self::exactKeys($projection['metadata'], [
            'redcms_intent_state_sha256', 'redcms_offer_state_sha256',
        ])
            && hash_equals(
                $intent['intentStateSha256'],
                (string) $projection['metadata']
                    ['redcms_intent_state_sha256']
            )
            && hash_equals(
                $intent['offerStateSha256'],
                (string) $projection['metadata']
                    ['redcms_offer_state_sha256']
            );
    }

    private static function identifier(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/D', $value) === 1;
    }

    private static function text(mixed $value, int $max, bool $empty): bool
    {
        return is_string($value)
            && ($empty || $value !== '')
            && trim($value) === $value
            && strlen($value) <= $max
            && preg_match('//u', $value) === 1
            && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }

    private static function httpsUrl(mixed $value): bool
    {
        if (!is_string($value)
            || strlen($value) < 1
            || strlen($value) > 2048
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }
        $url = parse_url($value);
        return is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && is_string($url['host'] ?? null)
            && $url['host'] !== ''
            && !array_key_exists('user', $url)
            && !array_key_exists('pass', $url)
            && !array_key_exists('port', $url)
            && !array_key_exists('query', $url)
            && !array_key_exists('fragment', $url)
            && is_string($url['path'] ?? null)
            && $url['path'] !== '/';
    }

    private static function sameOrigin(string $left, string $right): bool
    {
        $a = parse_url($left);
        $b = parse_url($right);
        return is_array($a)
            && is_array($b)
            && ($a['scheme'] ?? '') === ($b['scheme'] ?? '')
            && ($a['host'] ?? '') === ($b['host'] ?? '');
    }

    private static function sessionId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Acs_test_[A-Za-z0-9_]{16,160}\z/D', $value) === 1;
    }

    private static function checkoutUrl(mixed $value, string $sessionId): bool
    {
        if (!is_string($value)
            || strlen($value) > 4096
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }
        $url = parse_url($value);
        return is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && ($url['host'] ?? null) === 'checkout.stripe.com'
            && !array_key_exists('user', $url)
            && !array_key_exists('pass', $url)
            && !array_key_exists('port', $url)
            && !array_key_exists('query', $url)
            && ($url['path'] ?? null) === '/c/pay/' . $sessionId
            && (!array_key_exists('fragment', $url)
                || self::text($url['fragment'], 2048, false));
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function hash(array $value): string
    {
        try {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return hash('sha256', $encoded);
    }

    private static function prepareInvalid(string $error): array
    {
        return [
            'valid' => false,
            'contract' => null,
            'contractSha256' => '',
            'errors' => [$error],
        ];
    }

    private static function acceptInvalid(string $error): array
    {
        return [
            'valid' => false,
            'handoff' => null,
            'contractSha256' => '',
            'responseEvidenceSha256' => '',
            'resultSha256' => '',
            'errors' => [$error],
        ];
    }
}
