<?php

namespace App\Helpers;

use App\Actions\Common\BaseJsonResource;
use AshAllenDesign\ShortURL\Classes\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function null_resource(): JsonResource
{
    return new BaseJsonResource(null);
}

function get_permissions_by_routes(): array
{
    $routeCollection = Route::getRoutes()->get();
    $permissions = [];

    foreach ($routeCollection as $item) {
        $name = $item->action;
        if (!empty($name['as'])) {
            $permission = $name['as'];
            $permission = trim(strtolower($permission));
            $ignoreRoutesStartingWith = 'sanctum|livewire|ignition|notifications|log-viewer|debugbar';
            $permissionFilled = trim(str_replace('user management ', '', $permission));
            if (preg_match("($ignoreRoutesStartingWith)", $permission) === 0 && filled($permissionFilled)) {
                $method = $item->getActionMethod();

                if (strpos($method, '\\') !== false) {
                    $method = '__invoke';
                }

                $permissions[] = ['name' => $permissionFilled, 'method' => $method];
            }
        }
    }

    return get_modules_array_from_permissions($permissions);
}

function get_modules_array_from_permissions(array $permissions): array
{
    $modules = [];

    foreach ($permissions as $item) {
        $parts = explode('.', $item['name']);
        $module = $parts[0];
        $submodule = implode('.', array_slice($parts, 1));

        if (!isset($modules[$module])) {
            $modules[$module] = [];
        }

        if (!in_array($submodule, $modules[$module])) {
            array_push($modules[$module], ['name' => $submodule, 'method' => $item['method']]);
        }
    }

    foreach ($modules as &$submodules) {
        sort($submodules);
    }

    return $modules;
}

function get_all_includes(): array
{
    $includes = request()->input('include');
    if ($includes === null) {
        return [];
    } elseif (is_array($includes)) {
        return $includes;
    } else {
        return explode(',', $includes);
    }
}

function get_all_includes_in_camel_case(): array
{
    return collect(get_all_includes())
        ->map(function (string $includes) {
            return collect(explode('.', $includes))
                ->map(fn(string $include) => Str::camel($include))
                ->join('.');
        })
        ->toArray();
}

function get_all_appends(): array
{
    $appends = request()->input('append');
    if ($appends === null) {
        return [];
    } elseif (is_array($appends)) {
        return $appends;
    } else {
        return explode(',', $appends);
    }
}

function is_include_present(string $include): bool
{
    return in_array(Str::snake($include), get_all_includes());
}

function is_append_present(string $append): bool
{
    return in_array(Str::snake($append), get_all_appends());
}

function get_permissions_as_modules_array(mixed $permissions): array
{
    $finalPermissions = [];
    $modules = $permissions->where('parent_module_name', null)->pluck('name')->toArray();
    $modulesThroughSubmodules = $permissions->pluck('name');

    foreach ($modulesThroughSubmodules as $key => $submodule) {
        try {
            $moduleName = explode('.', $submodule)[0];
            if (!in_array($moduleName, $modules)) {
                $modules[] = $moduleName;
            }
        } catch (\Exception $e) {
            //
        }
    }

    foreach ($modules as $module) {
        $modulePermissions = $permissions->filter(function ($permission) use ($module) {
            return strpos($permission['name'], $module) === 0 && $permission['name'] !== $module;
        })->map(function ($permission) use ($module) {
            $name = Str::ucfirst(Str::replace('.', ' ', Str::replace("{$module}.", '', $permission['name'])));

            return [
                'id' => $permission['id'],
                'name' => match ($name) {
                    'Index' => 'Can view records',
                    'Destroy' => 'Can delete records',
                    'Show' => 'Can view record details',
                    'Store' => 'Can save new record',
                    'Update' => 'Can update old record',
                    default => $name
                },
            ];
        })->toArray();

        $moduleObject = [
            'name' => $module,
            'submodules' => array_values($modulePermissions),
        ];

        $finalPermissions[] = $moduleObject;
    }

    return $finalPermissions;
}

function shouldAppend(string $append): bool
{
    $appends = [];

    if (request()->has('append')) {
        $appends = explode(',', request()->get('append'));
    }

    if (!in_array($append, $appends)) {
        return false;
    }

    return true;
}

function formatCommas($address): string
{
    // Remove commas followed by a space
    $address = preg_replace('/,(?=\s)/', '', $address);   // woh comma remove kro jis k agy space h

    // Remove commas not followed by a space
    $address = preg_replace('/,(?!\s)/', ' ', $address);

    // Remove extra spaces
    $address = preg_replace('/\s+/', ' ', $address);

    return $address;
}

function removeSpace(string $string): string
{
    return str_replace(' ', '', $string);
}

function extractFirstNumericNumber(string $input): ?string
{
    return trim(Str::before($input, ' '));
}
/**
 * Replace the only first occurance of a substring in a string
 */
function removeStringFromString(?string $needle, string $string, string $replaceString = ''): ?string
{
    return trim(Str::replaceFirst($needle ?? '', $replaceString, $string));
}
/**
 * returns only numbers from a string with space
 */
function getOnlyNumersFromString(?string $string): string
{
    if (!$string) {
        $string = '';
    }
    $cleanedString = preg_replace('/[^0-9.,\/-]/', ' ', $string);

    // Remove extra spaces at the end
    return $cleanedString = trim($cleanedString);
}

function replaceFirst(string $search, string $replace, string $subject): string
{
    return preg_replace('/' . preg_quote($search, '/') . '/', $replace, $subject, 1);
}

function fixNumberForAirCall(string $number): string
{
    return Str::start(substr(preg_replace('/\D/', '', $number), -10), '+44');
}

function generateUniqueRandomString(): string
{
    return str()->upper(Str::random(10));
}

function generateUniqueRandomStringWithTimeStamp(): string
{
    // Generate a random string of length 9 (10 - length of timestamp)
    $randomString = Str::random(9);

    // Get the current timestamp
    $timestamp = time();

    // Randomly choose a position to insert the timestamp
    $position = rand(0, 9); // Random position between 0 and 9 (inclusive)

    // Insert the timestamp into the random string at the chosen position
    $uniqueString = substr_replace($randomString, $timestamp, $position, 0);

    return strtoupper($uniqueString);
}

function base64url_encode($data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data): bool|string
{
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '='));
}

function meg_encrypt($data): string
{
    // it get the string, replace each character with our specified ascii value from config array
    $str = json_encode($data);
    $ourAsciiArray = config('encrypt.ascii_char');
    $result = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $stringChar = substr($str, $i, 1);
        $result .= chr(Arr::get($ourAsciiArray, ord($stringChar)));
        // $result .= chr(ord($stringChar) + 33);  // if the above not working we could replace it with a simple addition of a random ascii character

    }

    return base64url_encode($result);
}

function meg_decrypts($data)
{
    $str = base64url_decode($data);

    $ourAsciiArray = config('encrypt.ascii_char');

    $result = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $stringChar = substr($str, $i, 1);
        $result .= chr(Arr::get($ourAsciiArray, ord($stringChar)));
        // $result .= chr(ord($stringChar) - 33);

    }

    return json_decode($result, true);
}

function CopyFilefromSourceToDestination($source, $destination, $disk = 'public')
{
    /* based on your disk , set path ,if its local add public in the string , and if its public don't add . so bascically based on your disk, it starting path starts */
    if (!Storage::disk($disk)->exists($source)) {
        return [
            'success' => false,
            'message' => 'File not found',
        ];
    } else {

        $response = Storage::disk($disk)->copy($source, $destination);

        return [
            'success' => $response,
            'message' => 'File operation was ' . $response ? ' successful' : 'unsuccessful',
        ];
    }
}
/**
 * Removes all characters from a string after the first numeric character is found like 28A fron Address 28a road wala ghr
 *
 *
 * @return string
 */
function removetillFirstNuermicSpcae(?string $string)
{
    if (!$string) {
        $string = '';
    }
    if (!Str::endsWith($string, ' ')) {  // add a space at end if not present just to handle a case where number could be last in the string
        $string .= ' ';
    }
    $resultingString = '';
    $isNumericFound = '';
    for ($i = 0; $i < strlen($string); $i++) {
        $stringChar = substr($string, $i, 1);
        if (!$isNumericFound) {
            $isNumericFound = is_numeric($stringChar);
        }   // only check if its not found yet
        if ($isNumericFound && $stringChar === ' ') {
            $resultingString = substr($string, 0, $i);
            break;
        }
        // $result .= chr(Arr::get($ourAsciiArray, ord($stringChar)));
        // $result .= chr(ord($stringChar) + 33);  // if the above not working we could replace it with a simple addition of a random ascii character
    }

    return $resultingString;
}

/**
 * takes a postcode and add a space before last three characters
 *
 *
 * @param  string  $string
 */
function formatPostCodeWithSpace(string $postCode, int $indexFromLast = 3): string
{
    $postCode = rtrim($postCode);
    if (Str::contains($postCode, ' ')) {
        return $postCode;
    }

    $length = strlen($postCode);
    $reversedPostCode = strrev($postCode);
    $reversedPostCode = Str::substrReplace($reversedPostCode, ' ', 3, 0);

    return strrev($reversedPostCode);
}

function split_name($name)
{
    $parts = [];

    while (strlen(trim($name)) > 0) {
        $name = trim($name);
        $string = preg_replace('#.*\s([\w-]*)$#', '$1', $name);
        $parts[] = $string;
        $name = trim(preg_replace('#' . preg_quote($string, '#') . '#', '', $name));
    }

    if (empty($parts)) {
        return false;
    }

    $parts = array_reverse($parts);
    $name = [];
    $name['first_name'] = $parts[0];
    $name['middle_name'] = (isset($parts[2])) ? $parts[1] : '';
    $name['last_name'] = (isset($parts[2])) ? $parts[2] : (isset($parts[1]) ? $parts[1] : '');

    return $name;
}

function generateARandomNumberNotInGivenArray($array = []): int
{

    // Generate a random number until it's not in the array
    do {
        $randomNumber = mt_rand(1, 999999); // Adjust range as needed
    } while (in_array($randomNumber, $array));

    return $randomNumber;

}



function generateShortUrl(string $destinationUrl): string
{
    $shortURLObject = app(Builder::class)->destinationUrl($destinationUrl)->make();
    return $shortURL = $shortURLObject->default_short_url;
}
