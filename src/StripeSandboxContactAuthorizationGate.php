<?php

declare(strict_types=1);

/**
 * P3E-6 pure expiring operator-authorization envelope preparation.
 *
 * The output is hash-bound, not a cryptographic signature. A later stateful
 * runner must prove owner authority and atomically consume the nonce before
 * any provider contact can occur.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate
{
    public static function prepare(
        array $readiness,
        array $confirmation,
        string $evaluatedAtUtc
    ): array {
        if (!self::readiness($readiness)
            || !self::confirmation($confirmation)
            || !hash_equals(
                $readiness['planSha256'],
                $confirmation['planSha256']
            )
        ) {
            return self::refused('contact_authorization_refused');
        }

        $issuedAt = self::utc($confirmation['issuedAtUtc']);
        $expiresAt = self::utc($confirmation['expiresAtUtc']);
        $evaluatedAt = self::utc($evaluatedAtUtc);
        if ($issuedAt === null
            || $expiresAt === null
            || $evaluatedAt === null
            || $expiresAt->getTimestamp() <= $issuedAt->getTimestamp()
            || $expiresAt->getTimestamp() - $issuedAt->getTimestamp() > 900
            || $evaluatedAt->getTimestamp() < $issuedAt->getTimestamp()
            || $evaluatedAt->getTimestamp() >= $expiresAt->getTimestamp()
        ) {
            return self::refused('contact_authorization_expired');
        }

        $authorization = [
            'action' => 'authorize-stripe-sandbox-read-only-probe',
            'planSha256' => $readiness['planSha256'],
            'operatorSubjectSha256' =>
                $confirmation['operatorSubjectSha256'],
            'authorizationNonceSha256' =>
                $confirmation['authorizationNonceSha256'],
            'issuedAtUtc' => $confirmation['issuedAtUtc'],
            'expiresAtUtc' => $confirmation['expiresAtUtc'],
            'maximumAttempts' => 1,
            'oneTimeConsumptionRequired' => true,
            'ownerAuthorityRevalidationRequired' => true,
            'restrictedTestKeyRequired' => true,
            'readOnlyGetAuthorized' => true,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'checkoutCreationAuthorized' => false,
            'paymentAuthorized' => false,
            'webhookAuthorized' => false,
            'liveModeAuthorized' => false,
            'clientDeploymentAuthorized' => false,
            'credentialValueIncluded' => false,
            'contactAuthorized' => false,
            'executionPerformed' => false,
        ];
        $encoded = self::encode($authorization);
        if ($encoded === null) {
            return self::refused('contact_authorization_encoding_failed');
        }

        return [
            'prepared' => true,
            'authorization' => $authorization,
            'authorizationSha256' => hash('sha256', $encoded),
            'ownerAuthorityRevalidationRequired' => true,
            'nonceConsumptionRequired' => true,
            'contactAuthorized' => false,
            'executionPerformed' => false,
            'errors' => [],
        ];
    }

    private static function readiness(array $readiness): bool
    {
        if (!self::exactKeys($readiness, [
            'ready',
            'contactPlan',
            'planSha256',
            'executionPerformed',
            'errors',
        ])
            || ($readiness['ready'] ?? null) !== true
            || !is_array($readiness['contactPlan'] ?? null)
            || !self::sha256($readiness['planSha256'] ?? null)
            || ($readiness['executionPerformed'] ?? null) !== false
            || ($readiness['errors'] ?? null) !== []
        ) {
            return false;
        }
        $encoded = self::encode($readiness['contactPlan']);
        return $encoded !== null
            && hash_equals(
                $readiness['planSha256'],
                hash('sha256', $encoded)
            )
            && ($readiness['contactPlan']['operation'] ?? null)
                === 'stripe.sandbox.read-only-resource-miss-probe'
            && ($readiness['contactPlan']['method'] ?? null) === 'GET'
            && ($readiness['contactPlan']['maximumAttempts'] ?? null) === 1
            && ($readiness['contactPlan']['retryAuthorized'] ?? null) === false
            && ($readiness['contactPlan']['mutationAuthorized'] ?? null)
                === false
            && ($readiness['contactPlan']['checkoutCreationAuthorized'] ?? null)
                === false
            && ($readiness['contactPlan']['paymentAuthorized'] ?? null) === false
            && ($readiness['contactPlan']['liveModeAuthorized'] ?? null) === false
            && ($readiness['contactPlan']['clientDeploymentAuthorized'] ?? null)
                === false
            && ($readiness['contactPlan']['executionPerformed'] ?? null)
                === false;
    }

    private static function confirmation(array $confirmation): bool
    {
        return self::exactKeys($confirmation, [
            'action',
            'planSha256',
            'operatorSubjectSha256',
            'authorizationNonceSha256',
            'issuedAtUtc',
            'expiresAtUtc',
            'confirmedRestrictedTestKey',
            'confirmedReadOnlyGet',
            'confirmedSingleAttempt',
            'confirmedNoRetry',
            'confirmedNoMutation',
            'confirmedNoCheckoutCreation',
            'confirmedNoPayment',
            'confirmedNoWebhook',
            'confirmedNoLiveMode',
            'confirmedNoClientDeployment',
            'credentialValueIncluded',
        ])
            && ($confirmation['action'] ?? null)
                === 'authorize-stripe-sandbox-read-only-probe'
            && self::sha256($confirmation['planSha256'] ?? null)
            && self::sha256($confirmation['operatorSubjectSha256'] ?? null)
            && self::sha256($confirmation['authorizationNonceSha256'] ?? null)
            && is_string($confirmation['issuedAtUtc'] ?? null)
            && is_string($confirmation['expiresAtUtc'] ?? null)
            && ($confirmation['confirmedRestrictedTestKey'] ?? null) === true
            && ($confirmation['confirmedReadOnlyGet'] ?? null) === true
            && ($confirmation['confirmedSingleAttempt'] ?? null) === true
            && ($confirmation['confirmedNoRetry'] ?? null) === true
            && ($confirmation['confirmedNoMutation'] ?? null) === true
            && ($confirmation['confirmedNoCheckoutCreation'] ?? null) === true
            && ($confirmation['confirmedNoPayment'] ?? null) === true
            && ($confirmation['confirmedNoWebhook'] ?? null) === true
            && ($confirmation['confirmedNoLiveMode'] ?? null) === true
            && ($confirmation['confirmedNoClientDeployment'] ?? null) === true
            && ($confirmation['credentialValueIncluded'] ?? null) === false;
    }

    private static function utc(string $value): ?DateTimeImmutable
    {
        if (preg_match(
            '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T'
                . '[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/D',
            $value
        ) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $value,
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable
            || (is_array($errors)
                && ($errors['warning_count'] !== 0
                    || $errors['error_count'] !== 0))
            || $date->format('Y-m-d\TH:i:s\Z') !== $value
        ) {
            return null;
        }
        return $date;
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

    private static function encode(array $value): ?string
    {
        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return null;
        }
    }

    private static function refused(string $error): array
    {
        return [
            'prepared' => false,
            'authorization' => null,
            'authorizationSha256' => '',
            'ownerAuthorityRevalidationRequired' => true,
            'nonceConsumptionRequired' => true,
            'contactAuthorized' => false,
            'executionPerformed' => false,
            'errors' => [$error],
        ];
    }
}
