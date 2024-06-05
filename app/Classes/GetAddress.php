<?php

namespace App\Classes;

use App\Traits\Jsonify;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GetAddress
{
    use Jsonify;

    public function getSuggestions(string $postCode)
    {
        $token = config('app.get_address_api');

        try {
            $postCode = Str::upper(str_replace(' ', '', preg_replace('/[^a-zA-Z0-9\s]/', ' ', $postCode)));

            $request = Http::withHeaders([
                'X-Api-Key' => $token,
            ])
                ->get("https://api.addressian.co.uk/v2/autocomplete/$postCode");

            $postCodeRequest = Http::withHeaders([
                'X-Api-Key' => $token,
            ])
                ->get("https://api.addressian.co.uk/v1/postcode/$postCode");

            if ($request->successful()) {
                $postCodeResponseCountry = Arr::get($postCodeRequest->json(), 'country', null);
                $addresses = $request->json();
                $result = [];

                foreach ($addresses as $key => $address) {
                    $transformedAddress = implode(' ', $address['address']);

                    if (isset($address['city'])) {
                        $transformedAddress .= ', ' . $address['city'];
                    }

                    if (isset($address['county'])) {
                        $transformedAddress .= ', ' . $address['county'];
                    }

                    if ($postCodeResponseCountry) {
                        $transformedAddress .= " -- $postCodeResponseCountry";
                    }

                    $result[] = [
                        'address' => $transformedAddress,
                        'post_code' => str_replace(' ', '', $address['postcode']),
                        'plain_address' => implode(' ', $address['address']),
                        'city' => $address['city'] ?? null,
                        'county' => $address['county'] ?? null,
                        'country' => $postCodeResponseCountry ?? null,
                        'building_number' => $address['buildingnumber'] ?? $address['buildingname'] ?? null,
                        'sub_building' => $address['subbuilding'] ?? null,
                        'raw_api_response' => $address,
                        'actual_post_code' => $address['postcode'],
                    ];
                }

                return $result;
            } else {
                return [];
            }
        } catch (Exception $e) {
            return [];
        }
    }

    public function adressionApi(string $postCode, string $query)
    {
        $token = config('app.get_address_api');
        try {
            $postCode = Str::upper(str_replace(' ', '', preg_replace('/[^a-zA-Z0-9\s]/', ' ', $postCode)));
            $query = str_replace("\n", ' ', str_replace('  ', ' ', preg_replace('/[^a-zA-Z0-9\s]/', ' ', $query)));
            $request = Http::withHeaders([
                'X-Api-Key' => $token,
            ])
                ->get("https://api.addressian.co.uk/v2/autocomplete/$postCode $query");

            $postCodeRequest = Http::withHeaders([
                'X-Api-Key' => $token,
            ])
                ->get("https://api.addressian.co.uk/v1/postcode/$postCode");

            if ($request->successful()) {
                $postCodeResponseCountry = Arr::get($postCodeRequest->json(), 'country', null);
                /* the beneath code is first matching the exact pose code to the result and modifying the addrees plus code for later use
                 and next thing is that if no exact address is found against our provided address then we are just using the first address */

                $Firstresult = $request?->collect()?->transform(function ($eachResult) {
                    return [
                        ...$eachResult,
                        'address' => str_replace("\n", ' ', str_replace('  ', ' ', preg_replace('/[^a-zA-Z0-9\s]/', ' ', implode(" ", $eachResult['address'])))),
                        'postcode' => Str::upper(str_replace(' ', '', preg_replace('/[^a-zA-Z0-9\s]/', ' ', $eachResult['postcode']))),
                        'actual_post_code' => $eachResult['postcode'],
                    ];
                });
                $Firstresult = $Firstresult->filter(function ($eachResult) use ($postCode) {
                    return $eachResult['postcode'] == $postCode;
                })->isNotEmpty() ? $Firstresult->filter(function ($eachResult) use ($postCode) {
                    return $eachResult['postcode'] == $postCode;
                }) : $Firstresult;
                // check for exact match if possible else old ka pehla
                $exactCheck = $Firstresult?->first(function (array $value, int $key) use ($query) {
                    return Str::contains($value['address'], $query);
                }) ?: $Firstresult?->first();


                $address = $exactCheck;
                $transformedAddress = $address['address'];

                if (isset($address['city'])) {
                    $transformedAddress .= ', ' . $address['city'];
                }

                if (isset($address['county'])) {
                    $transformedAddress .= ', ' . $address['county'];
                }

                if ($postCodeResponseCountry) {
                    $transformedAddress .= " -- $postCodeResponseCountry";
                }

                return [
                    str_replace(' ', '', $address['postcode']),
                    $transformedAddress,
                    $address['address'],
                    $address['city'] ?? null,
                    $address['county'] ?? null,
                    $postCodeResponseCountry ?? null,
                    $address['buildingnumber'] ?? $address['buildingname'] ?? null,
                    $address['subbuilding'] ?? null,
                    $address,
                    $address['actual_post_code'],


                ];
            } else {

                Log::channel('addresso_api')
                    ->info("Error in postcode:: $postCode and address:: $query");

                return [$postCode, $query, $query, null, null, null, null, null, null, null];
            }
        } catch (Exception $e) {
            Log::channel('addresso_api')
                ->info("Exception in postcode:: $postCode and address:: $query {$e->getMessage()}");
            return [$postCode, $query, $query, null, null, null, null, null, null, null];
        }
    }
}
