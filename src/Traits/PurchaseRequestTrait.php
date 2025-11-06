<?php

namespace Erilshk\Vinti4Pay\Traits;

use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;

trait PurchaseRequestTrait
{

    /**
     * Gera o purchaseRequest em base64
     */
    protected function buildPurchaseRequest(array $billing): string
    {
        $user = isset($billing['user']) ? $this->formatUserBillingData($billing['user']) : [];
        $billing = array_merge($user, $billing);

        $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];
        $missing = array_diff($required, array_keys($billing));
        if ($missing) {
            throw new Vinti4Exception("Missing billing fields: " . implode(', ', $missing));
        }

        // Campos permitidos
        $allowed = [
            'acctID',
            'acctInfo',
            'email',
            'addrMatch',
            'billAddrCity',
            'billAddrCountry',
            'billAddrLine1',
            'billAddrLine2',
            'billAddrLine3',
            'billAddrPostCode',
            'billAddrState',
            'shipAddrCity',
            'shipAddrCountry',
            'shipAddrLine1',
            'shipAddrLine2',
            'shipAddrLine3',
            'shipAddrPostCode',
            'shipAddrState',
            'workPhone',
            'mobilePhone'
        ];

        $allowedNested = [
            'acctInfo' => ['chAccAgeInd', 'chAccChange', 'chAccDate', 'chAccPwChange', 'chAccPwChangeInd', 'suspiciousAccActivity'],
            'workPhone' => ['cc', 'subscriber'],
            'mobilePhone' => ['cc', 'subscriber'],
        ];

        // Se addrMatch = 'Y', duplica o endereço para shipping
        if (($billing['addrMatch'] ?? 'N') === 'Y') {
            $billing['shipAddrCountry']  = $billing['billAddrCountry'];
            $billing['shipAddrCity']     = $billing['billAddrCity'];
            $billing['shipAddrLine1']    = $billing['billAddrLine1'];
            $billing['shipAddrLine2']    = $billing['billAddrLine2'] ?? null;
            $billing['shipAddrPostCode'] = $billing['billAddrPostCode'] ?? null;
            $billing['shipAddrState']    = $billing['billAddrState'] ?? null;
        }

        $final = [];
        foreach ($billing as $k => $v) {
            if (in_array($k, $allowed, true)) {
                if (is_array($v) && isset($allowedNested[$k])) {
                    $final[$k] = array_intersect_key($v, array_flip($allowedNested[$k]));
                } elseif (!is_array($v)) {
                    $final[$k] = $v;
                }
            }
        }

        $json = json_encode($final, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Vinti4Exception("Failed to encode purchaseRequest to JSON.");
        }

        return base64_encode($json);
    }


    /**
     * Formata os dados de billing para 3DS purchaseRequest
     */
    protected function formatUserBillingData(array $user): array
    {
        $billing = [];

        // Função interna para pegar valor de array ou objeto
        $get = fn($key, $default = null) => is_array($user) ? ($user[$key] ?? $default) : ($user->$key ?? $default);

        // Função interna para pegar o countryCode do numero de telefone
        $extractCC = fn(string $phone, $default = ''): ?string => preg_match(
            '/^(?:\+|00)?(\d{1,3})(?:\d{6,12})$/',
            preg_replace('/[\s\-\(\)]/', '', $phone),
            $m
        ) ? $m[1] : $default;

        // Campos básicos
        if ($email = $get('email')) $billing['email'] = $email;
        if ($country = $get('country')) $billing['billAddrCountry'] = $country;
        if ($city = $get('city')) $billing['billAddrCity'] = $city;
        if ($line1 = $get('address', $get('address1'))) $billing['billAddrLine1'] = $line1;
        if ($line2 = $get('address2')) $billing['billAddrLine2'] = $line2;
        if ($line3 = $get('address3')) $billing['billAddrLine3'] = $line3;
        if ($postcode = $get('postCode')) $billing['billAddrPostCode'] = $postcode;
        if ($state = $get('state')) $billing['billAddrState'] = $state;

        // Telefones
        if ($mobile = $get('mobilePhone', $get('phone'))) {
            $cc = $extractCC($mobile, '238');
            $billing['mobilePhone'] = [
                'cc' => $get('mobilePhoneCC', $cc),
                'subscriber' => $mobile
            ];
        }

        if ($work = $get('workPhone')) {
            $cc = $extractCC($mobile, '238');
            $billing['workPhone'] = [
                'cc' => $get('workPhoneCC', $cc),
                'subscriber' => $work
            ];
        }

        // Campos acctInfo para 3DS
        $acctID = $get('id');
        $createdAt = $get('created_at');
        $updatedAt = $get('updated_at');
        $suspicious = $get('suspicious');

        if ($acctID || $createdAt || $updatedAt || isset($suspicious)) {
            $billing['acctID'] = $acctID ?? '';
            $billing['acctInfo'] = [
                'chAccAgeInd' => $get('chAccAgeInd', '01'), // opcional, default 01
                'chAccChange' => $updatedAt ? date('Ymd', strtotime($updatedAt)) : '',
                'chAccDate' => $createdAt ? date('Ymd', strtotime($createdAt)) : '',
                'chAccPwChange' => $updatedAt ? date('Ymd', strtotime($updatedAt)) : '',
                'chAccPwChangeInd' => $get('chAccPwChangeInd', '01'),
                'suspiciousAccActivity' => isset($suspicious) ? ($suspicious ? '02' : '01') : ''
            ];

            // Remove campos vazios dentro de acctInfo
            $billing['acctInfo'] = array_filter($billing['acctInfo'], fn($v) => $v !== null && $v !== '');
            if (empty($billing['acctInfo'])) unset($billing['acctInfo']);
            if ($billing['acctID'] === '') unset($billing['acctID']);
        }

        // Remove campos vazios do nível superior
        $billing = array_filter($billing, fn($v) => $v !== null && $v !== '');

        return $billing;
    }
}
