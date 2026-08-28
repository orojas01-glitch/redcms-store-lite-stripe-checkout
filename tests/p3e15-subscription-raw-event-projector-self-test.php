<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/package/StripeSandboxSubscriptionRawEventProjector.php';
$assertions = 0;
$assert = static function(bool $ok,string $m)use(&$assertions){$assertions++;if(!$ok)throw new RuntimeException($m);};
$intent='sint_'.str_repeat('1',32);$offer=str_repeat('2',64);
$make = static function(
    string $type,
    array $object,
    string $apiVersion = '2024-09-30.acacia'
) use($intent,$offer): array {
    $event=['id'=>'evt_ProjectorEvent123456','object'=>'event','api_version'=>$apiVersion,
        'created'=>1787630500,'data'=>['object'=>$object],'livemode'=>false,'type'=>$type];
    $envelope=['valid'=>true,'verification'=>'verified','providerEnvironment'=>'sandbox',
        'apiVersion'=>$apiVersion,'eventType'=>$type,
        'eventRefSha256'=>hash('sha256',$event['id']),'eventCreatedAt'=>$event['created'],
        'objectType'=>$object['object'],'objectProjectionSha256'=>hash('sha256',json_encode($object,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),
        'receivedAt'=>1787630600,'rawBodySha256'=>str_repeat('3',64),
        'signatureEvidenceSha256'=>str_repeat('4',64)];
    return [$envelope,$event];
};
try {
    $metadata=['redcms_intent_reference'=>$intent,'redcms_offer_state_sha256'=>$offer];
    $cases=[
        ['checkout.session.completed',['id'=>'cs_test_ProjectorCompleted123456','object'=>'checkout.session',
            'client_reference_id'=>$intent,'metadata'=>['redcms_offer_state_sha256'=>$offer],
            'status'=>'complete','payment_status'=>'paid','subscription'=>['id'=>'sub_Projector123456','current_period_end'=>1790308800],
            'customer_details'=>['email'=>'private@example.test']],'complete_paid'],
        ['checkout.session.expired',['id'=>'cs_test_ProjectorExpired123456','object'=>'checkout.session',
            'client_reference_id'=>$intent,'metadata'=>['redcms_offer_state_sha256'=>$offer],
            'status'=>'expired','payment_status'=>'unpaid','subscription'=>null],'expired'],
        ['invoice.paid',['id'=>'in_ProjectorPaid123456','object'=>'invoice','subscription'=>'sub_Projector123456',
            'subscription_details'=>['metadata'=>$metadata],'period_end'=>1790308800,'status'=>'paid','paid'=>true,
            'customer_email'=>'private@example.test','payment_settings'=>['x'=>'secret']],'paid_active'],
        ['invoice.payment_failed',['id'=>'in_ProjectorFailed123456','object'=>'invoice','subscription'=>'sub_Projector123456',
            'subscription_details'=>['metadata'=>$metadata],'period_end'=>1790308800,'status'=>'open','paid'=>false],'payment_failed'],
        ['customer.subscription.deleted',['id'=>'sub_Projector123456','object'=>'subscription','metadata'=>$metadata,
            'current_period_end'=>1790308800,'status'=>'canceled','customer'=>'cus_Private123456'],'canceled'],
    ];
    foreach($cases as [$type,$object,$status]){
        [$envelope,$event]=$make($type,$object);
        $result=RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project($envelope,$event);
        $assert($result['valid']&&$result['verifiedEvent']['providerStatus']===$status,
            'supported event projects');
        $encoded=json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $assert(!str_contains($encoded,'private@example.test')&&!str_contains($encoded,'cus_Private')
            && !$result['customerDataIncluded']&&!$result['paymentMethodDataIncluded']
            && !$result['addressDataIncluded']&&!$result['rawEventIncluded'],'private fields excluded');
    }
    [$currentEnvelope,$currentEvent]=$make(
        $cases[1][0],
        $cases[1][1],
        '2026-07-29.dahlia'
    );
    $currentResult=RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project(
        $currentEnvelope,
        $currentEvent
    );
    $assert(
        $currentResult['valid']
            && $currentResult['verifiedEvent']['providerStatus']==='expired',
        'current Dashboard Sandbox API version projects the bounded event'
    );
    $resignedEnvelope=$currentEnvelope;
    $resignedEnvelope['receivedAt']+=120;
    $resignedEnvelope['signatureEvidenceSha256']=str_repeat('5',64);
    $resignedResult=
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project(
            $resignedEnvelope,
            $currentEvent
        );
    $assert(
        $resignedResult['valid']
            && $resignedResult['signatureEvidenceSha256']
                !==$currentResult['signatureEvidenceSha256']
            && $resignedResult['verifiedEvent']['receivedAt']
                !==$currentResult['verifiedEvent']['receivedAt']
            && $resignedResult['verifiedEvent']['eventEvidenceSha256']
                ===$currentResult['verifiedEvent']['eventEvidenceSha256'],
        'fresh delivery signatures retain immutable event evidence'
    );
    [$deferredEnvelope,$deferredEvent]=$make(
        'checkout.session.completed',
        ['id'=>'cs_test_ProjectorDeferred123456','object'=>'checkout.session',
            'client_reference_id'=>$intent,
            'metadata'=>['redcms_offer_state_sha256'=>$offer],
            'status'=>'complete','payment_status'=>'paid',
            'subscription'=>'sub_ProjectorDeferred123456'],
        '2026-07-29.dahlia'
    );
    $deferred=RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project(
        $deferredEnvelope,
        $deferredEvent
    );
    $assert(
        $deferred['valid']
            && $deferred['verifiedEvent']['providerStatus']
                ==='complete_paid_deferred'
            && $deferred['verifiedEvent']['providerSubscriptionRef']
                ==='sub_ProjectorDeferred123456'
            && $deferred['verifiedEvent']['currentPeriodEndEpoch']===null,
        'unexpanded completed Checkout is projected for terminal deferral'
    );
    $currentInvoice=[
        'id'=>'in_ProjectorCurrentPaid123456','object'=>'invoice',
        'parent'=>['type'=>'subscription_details','subscription_details'=>[
            'metadata'=>$metadata,
            'subscription'=>'sub_ProjectorCurrent123456',
        ]],
        'lines'=>['object'=>'list','data'=>[ [
            'object'=>'line_item','livemode'=>false,'metadata'=>$metadata,
            'parent'=>['type'=>'subscription_item_details',
                'subscription_item_details'=>[
                    'subscription'=>'sub_ProjectorCurrent123456',
                ]],
            'period'=>['start'=>1787630500,'end'=>1790308800],
            'customer_email'=>'private@example.test',
        ]]],
        'period_end'=>1787630500,'status'=>'paid','paid'=>true,
        'customer_email'=>'private@example.test',
    ];
    [$invoiceEnvelope,$invoiceEvent]=$make(
        'invoice.paid',
        $currentInvoice,
        '2026-07-29.dahlia'
    );
    $invoiceResult=
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project(
            $invoiceEnvelope,
            $invoiceEvent
        );
    $assert(
        $invoiceResult['valid']
            && $invoiceResult['verifiedEvent']['intentReference']===$intent
            && $invoiceResult['verifiedEvent']['providerSubscriptionRef']
                ==='sub_ProjectorCurrent123456'
            && $invoiceResult['verifiedEvent']['currentPeriodEndEpoch']
                ===1790308800
            && !str_contains(
                json_encode($invoiceResult,JSON_THROW_ON_ERROR),
                'private@example.test'
            ),
        'current invoice parent and matching line project bounded lifecycle facts'
    );
    $mixedInvoice=$currentInvoice;
    $mixedInvoice['lines']['data'][0]['period']['end']=1790400000;
    $mixedInvoice['lines']['data'][]=$currentInvoice['lines']['data'][0];
    [$mixedEnvelope,$mixedEvent]=$make(
        'invoice.paid',
        $mixedInvoice,
        '2026-07-29.dahlia'
    );
    $assert(
        !RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project(
            $mixedEnvelope,
            $mixedEvent
        )['valid'],
        'mixed current invoice subscription periods are refused'
    );
    $mismatchedEnvelope=$currentEnvelope;
    $mismatchedEnvelope['apiVersion']='2024-09-30.acacia';
    $assert(
        !RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project(
            $mismatchedEnvelope,
            $currentEvent
        )['valid'],
        'event and verified-envelope API versions must match'
    );
    [$envelope,$event]=$make($cases[2][0],$cases[2][1]);
    $event['data']['object']['subscription_details']['metadata']['redcms_intent_reference']='bad';
    $assert(!RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project($envelope,$event)['valid'],
        'object drift from signature envelope refused');
    [$envelope,$event]=$make($cases[2][0],array_replace_recursive($cases[2][1],[
        'subscription_details'=>['metadata'=>['redcms_intent_reference'=>'bad']],
    ]));
    $assert(!RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::project($envelope,$event)['valid'],
        'invalid correlation metadata refused after envelope binding');
    $assert(hash_equals(hash_file('sha256',$root.'/src/StripeSandboxSubscriptionRawEventProjector.php'),
        hash_file('sha256',$root.'/package/StripeSandboxSubscriptionRawEventProjector.php')),
        'copies identical');
    echo 'Stripe raw subscription-event projector passed '.$assertions." assertions.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
